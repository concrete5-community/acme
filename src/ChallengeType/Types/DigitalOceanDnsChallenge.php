<?php

namespace Acme\ChallengeType\Types;

use Acme\ChallengeType\ChallengeTypeInterface;
use Acme\Entity\AuthorizationChallenge;
use Acme\Entity\Domain;
use Acme\Exception\RuntimeException;
use Acme\Http\ClientFactory as HttpClientFactory;
use Acme\Security\Crypto;
use ArrayAccess;
use Concrete\Core\Filesystem\ElementManager;
use Concrete\Core\Page\Page;
use Exception;
use Throwable;
use Zend\Http\Exception\RuntimeException as HttpClientRuntimeException;
use Zend\Http\Header\Authorization;
use Zend\Http\Header\ContentType;
use Zend\Http\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class DigitalOceanDnsChallenge implements ChallengeTypeInterface
{
    /**
     * @var \Acme\Security\Crypto
     */
    protected $crypto;

    /**
     * @var \Acme\Http\ClientFactory
     */
    protected $httpClientFactory;

    /**
     * @var string
     */
    protected $handle;

    /**
     * Initialize the instance.
     *
     * @param HttpClientFactory $httpClientFactory
     * @param Crypto $crypto
     */
    public function __construct(Crypto $crypto, HttpClientFactory $httpClientFactory)
    {
        $this->crypto = $crypto;
        $this->httpClientFactory = $httpClientFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::initialize()
     */
    public function initialize($handle, array $challengeTypeOptions)
    {
        $this->handle = $handle;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::getHandle()
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::getName()
     */
    public function getName()
    {
        return t('DigitalOcean DNS Server');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::getAcmeTypeName()
     */
    public function getAcmeTypeName()
    {
        return 'dns-01';
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::getConfigurationDefinition()
     */
    public function getConfigurationDefinition()
    {
        return [
            'apiToken' => [
                'description' => t('The Personal Access Token'),
                'defaultValue' => '',
            ],
            'digitalOceanDomain' => [
                'description' => t('The root domain managed by DigitalOcean'),
                'defaultValue' => '',
            ],
            'recordSuffix' => [
                'description' => t('The suffix for the DNS records'),
                'defaultValue' => '',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::checkConfiguration()
     */
    public function checkConfiguration(Domain $domain, array $challengeConfiguration, ArrayAccess $errors)
    {
        $failed = false;
        $result = [
            'apiToken' => trim((string) array_get($challengeConfiguration, 'apiToken')),
        ];
        if ($result['apiToken'] === '') {
            $errors[] = t('The Personal Access Token must be specified');
            $failed = true;
        }
        if (!$failed) {
            $recordID = null;
            try {
                list($digitalOceanDomain, $recordSuffix) = $this->getDigitalOceanDomain($domain, $result['apiToken']);
                $result['digitalOceanDomain'] = $digitalOceanDomain;
                $result['recordSuffix'] = $recordSuffix;
                $recordID = $this->createDnsRecord(
                    '_acmetest-' . str_replace('.', '_', (string) microtime(true)) . $result['recordSuffix'],
                    '123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
                    $result['digitalOceanDomain'],
                    $result['apiToken']
                );
                $this->deleteDnsRecord($recordID, $result['digitalOceanDomain'], $result['apiToken']);
                $recordID = null;
            } catch (RuntimeException $x) {
                $errors[] = t('Failed to communicate with the DigitalOcean API server: %s', $x->getMessage());
                $failed = true;
            } finally {
                if ($recordID !== null) {
                    try {
                        $this->deleteDnsRecord($recordID, $result['digitalOceanDomain'], $result['apiToken']);
                    } catch (Exception $x) {
                    } catch (Throwable $x) {
                    }
                }
            }
        }

        return $failed ? null : $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::getDomainConfigurationElement()
     */
    public function getDomainConfigurationElement(Domain $domain, ElementManager $elementManager, Page $page)
    {
        $apiToken = '';
        if ($domain->getChallengeTypeHandle() === $this->getHandle()) {
            $savedConfiguration = $domain->getChallengeTypeConfiguration();
            $apiToken = array_get($savedConfiguration, 'apiToken', '');
            if (!is_string($apiToken)) {
                $apiToken = '';
            }
        }

        return $elementManager->get(
            'challenge_type/' . $this->getHandle(),
            'acme',
            $page,
            [
                'apiToken' => $apiToken,
            ]
        );
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::beforeChallenge()
     */
    public function beforeChallenge(AuthorizationChallenge $authorizationChallenge)
    {
        $configuration = $authorizationChallenge->getDomain()->getChallengeTypeConfiguration();
        $this->createDnsRecord(
            '_acme-challenge' . $configuration['recordSuffix'],
            $this->crypto->generateDnsRecordValue($authorizationChallenge->getChallengeAuthorizationKey()),
            $configuration['digitalOceanDomain'],
            $configuration['apiToken']
        );
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::afterChallenge()
     */
    public function afterChallenge(AuthorizationChallenge $authorizationChallenge)
    {
        $configuration = $authorizationChallenge->getDomain()->getChallengeTypeConfiguration();
        $uri = "https://api.digitalocean.com/v2/domains/{$configuration['digitalOceanDomain']}/records?" . http_build_query([
            'name' => '_acme-challenge' . $configuration['recordSuffix'] . '.' . $configuration['digitalOceanDomain'],
            'type' => 'TXT',
            'per_page' => '200',
        ]);
        $client = $this->createClient($configuration['apiToken'])
            ->setMethod('GET')
            ->setUri($uri)
        ;
        try {
            $response = $client->send();
            if ($response->isSuccess()) {
                $data = json_decode($response->getBody(), true);
                if (is_array($data) && isset($data['domain_records'])) {
                    $records = $data['domain_records'];
                    if (is_array($records)) {
                        $recordData = $this->crypto->generateDnsRecordValue($authorizationChallenge->getChallengeAuthorizationKey());
                        foreach ($records as $record) {
                            if (isset($record['data']) && $record['data'] === $recordData) {
                                if (isset($record['id']) && is_numeric($record['id'])) {
                                    $this->deleteDnsRecord($record['id'], $configuration['digitalOceanDomain'], $configuration['apiToken']);
                                }
                                break;
                            }
                        }
                    }
                }
            }
        } catch (Exception $x) {
        }
    }

    /**
     * @param string $apiToken
     *
     * @throws \Acme\Exception\RuntimeException
     *
     * @return string[]
     */
    protected function getDigitalOceanDomain(Domain $domain, $apiToken)
    {
        $chunks = explode('.', $domain->getPunycode());
        $tryMe = array_pop($chunks);
        $client = $this->createClient($apiToken)->setMethod('GET');
        while ($chunks !== []) {
            $tryMe = array_pop($chunks) . '.' . $tryMe;
            $client->setUri("https://api.digitalocean.com/v2/domains/{$tryMe}");
            try {
                $response = $client->send();
            } catch (HttpClientRuntimeException $x) {
                throw new RuntimeException($x->getMessage(), null, $x);
            }
            switch ($response->getStatusCode()) {
                case 404:
                    break;
                case 200:
                    $json = $response->getBody();
                    $data = json_decode($json, true);
                    if (!is_array($data)) {
                        throw new RuntimeException(t('Unexpected response'));
                    }

                    return [$tryMe, $chunks === [] ? '' : ('.' . implode('.', $chunks))];
                default:
                    throw new RuntimeException($this->describeErrorResponse($response));
            }
        }
        throw new RuntimeException(t('The domain %s could not be found: please be sure you are managing its DNS records with DigitalOcean.', $domain->getPunycode()));
    }

    /**
     * @param string $name
     * @param string $value
     * @param string $digitalOceanDomain
     * @param string $apiToken
     *
     * @return int
     */
    protected function createDnsRecord($name, $value, $digitalOceanDomain, $apiToken)
    {
        $client = $this->createClient($apiToken)
            ->setUri("https://api.digitalocean.com/v2/domains/{$digitalOceanDomain}/records")
            ->setMethod('POST')
            ->setRawBody(json_encode([
                'type' => 'TXT',
                'name' => $name,
                'data' => $value,
                'ttl' => 30,
            ], JSON_UNESCAPED_SLASHES))
        ;
        try {
            $response = $client->send();
        } catch (HttpClientRuntimeException $x) {
            throw new RuntimeException($x->getMessage(), null, $x);
        }
        if (!$response->isSuccess()) {
            throw new RuntimeException($this->describeErrorResponse($response));
        }
        $json = $response->getBody();
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException(t('Unexpected response'));
        }
        $recordID = array_get($data, 'domain_record.id');
        if (!is_numeric($recordID)) {
            throw new RuntimeException(t('Unexpected response'));
        }

        return $recordID;
    }

    /**
     * @param int $recordID
     * @param string $digitalOceanDomain
     * @param string $apiToken
     */
    protected function deleteDnsRecord($recordID, $digitalOceanDomain, $apiToken)
    {
        $client = $this->createClient($apiToken)
            ->setUri("https://api.digitalocean.com/v2/domains/{$digitalOceanDomain}/records/{$recordID}")
            ->setMethod('DELETE')
        ;
        try {
            $response = $client->send();
        } catch (HttpClientRuntimeException $x) {
            throw new RuntimeException($x->getMessage(), null, $x);
        }
        if (!$response->isSuccess()) {
            throw new RuntimeException($this->describeErrorResponse($response));
        }
    }

    /**
     * @param string $apiToken
     *
     * @return \Concrete\Core\Http\Client\Client
     */
    protected function createClient($apiToken)
    {
        $client = $this->httpClientFactory->getClient(true);
        $client->getRequest()->getHeaders()->addHeader(new Authorization("Bearer {$apiToken}"));
        $client->getRequest()->getHeaders()->addHeader(new ContentType('application/json'));

        return $client;
    }

    /**
     * @return string
     */
    protected function describeErrorResponse(Response $response)
    {
        try {
            $json = $response->getBody();
            $data = json_decode($json, true);
            if (is_array($data)) {
                $message = array_get($data, 'message');
                if (is_string($message) && $message !== '') {
                    return $message;
                }
            }
        } catch (Exception $x) {
        } catch (Throwable $x) {
            throw new RuntimeException(t('Unexpected response'));
        }

        return $response->getStatusCode() . ' (' . $response->getReasonPhrase() . ')';
    }
}
