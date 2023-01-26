<?php

namespace Acme\ChallengeType\Types;

use Acme\Entity\AuthorizationChallenge;
use Acme\Entity\Domain;
use Acme\Exception\RuntimeException;
use Acme\Http\ClientFactory as HttpClientFactory;
use Acme\Http\Response;
use Acme\Service\DNSChecker;
use ArrayAccess;
use Concrete\Core\Filesystem\ElementManager;
use Concrete\Core\Page\Page;
use Exception;
use Psr\Log\LoggerInterface;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

final class DigitalOceanDnsChallenge extends DnsChallenge
{
    /**
     * @var \Acme\Http\ClientFactory
     */
    private $httpClientFactory;

    /**
     * @var \Acme\Service\DNSChecker
     */
    private $dnsChecker;

    /**
     * @var string
     */
    private $handle;

    /**
     * @var bool
     */
    private $queryAuthoritativeNameservers;

    /**
     * @var float
     */
    private $maxDnsWaitSeconds;

    public function __construct(HttpClientFactory $httpClientFactory, DNSChecker $dnsChecker)
    {
        $this->httpClientFactory = $httpClientFactory;
        $this->dnsChecker = $dnsChecker;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::initialize()
     */
    public function initialize($handle, array $challengeTypeOptions)
    {
        $this->handle = $handle;
        $this->queryAuthoritativeNameservers = (bool) $challengeTypeOptions['query_authoritative_nameservers'];
        $this->maxDnsWaitSeconds = max(1.0, (float) $challengeTypeOptions['max_dns_wait_seconds']);
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
                'derived' => true,
            ],
            'recordSuffix' => [
                'description' => t('The suffix for the DNS records'),
                'defaultValue' => '',
                'derived' => true,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::checkConfiguration()
     */
    public function checkConfiguration(Domain $domain, array $challengeConfiguration, array $previousChallengeConfiguration, ArrayAccess $errors)
    {
        $failed = false;
        $result = [
            'apiToken' => trim((string) array_get($challengeConfiguration, 'apiToken')),
        ];
        if ($result['apiToken'] === '') {
            $result['apiToken'] = (string) array_get($previousChallengeConfiguration, 'apiToken', '');
            if ($result['apiToken'] === '') {
                $errors[] = t('The Personal Access Token must be specified');
                $failed = true;
            }
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
                'apiTokenConfigured' => $apiToken !== '',
            ]
        );
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::beforeChallenge()
     */
    public function beforeChallenge(AuthorizationChallenge $authorizationChallenge, LoggerInterface $logger)
    {
        $domain = $authorizationChallenge->getDomain();
        $configuration = $domain->getChallengeTypeConfiguration();
        $recordName = '_acme-challenge' . $configuration['recordSuffix'];
        $recordValue = $this->generateDnsRecordValue($authorizationChallenge->getChallengeAuthorizationKey());
        $recordID = $this->createDnsRecord(
            $recordName,
            $recordValue,
            $configuration['digitalOceanDomain'],
            $configuration['apiToken']
        );
        $logger->debug(t('Created record named %1$s with ID %2$s for domain %3$s. Its value is "%4$s"', $recordName, $recordID, $domain->getHostDisplayName(), $recordValue));
        $this->waitDnsReady($configuration['digitalOceanDomain'], $recordName, $recordValue, $logger);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::afterChallenge()
     */
    public function afterChallenge(AuthorizationChallenge $authorizationChallenge, LoggerInterface $logger)
    {
        $logger->debug(t('Looking for DNS records'));
        $configuration = $authorizationChallenge->getDomain()->getChallengeTypeConfiguration();
        $url = "https://api.digitalocean.com/v2/domains/{$configuration['digitalOceanDomain']}/records?" . http_build_query([
            'name' => '_acme-challenge' . $configuration['recordSuffix'] . '.' . $configuration['digitalOceanDomain'],
            'type' => 'TXT',
            'per_page' => '200',
        ]);
        $headers = $this->createClientHeaders($configuration['apiToken']);
        try {
            $response = $this->httpClientFactory->getClient()->get($url, $headers);
        } catch (RuntimeException $x) {
            $logger->debug(t('Operation failed with error %s', $x->getMessage()));
            $response = null;
        }
        if ($response === null) {
            return;
        }
        $records = null;
        if ($response->statusCode >= 200 && $response->statusCode < 300) {
            $data = json_decode($response->body, true);
            if (is_array($data) && isset($data['domain_records']) && is_array($data['domain_records'])) {
                $records = $data['domain_records'];
            }
        }
        if ($records === null) {
            $logger->debug(implode("\n", [
                t('Invalid response code: %s', $response->statusCode),
                t('Response body: %s', $response->body),
            ]));

            return;
        }
        $recordData = $this->generateDnsRecordValue($authorizationChallenge->getChallengeAuthorizationKey());
        foreach ($records as $record) {
            if (isset($record['data']) && $record['data'] === $recordData) {
                if (isset($record['id']) && is_numeric($record['id'])) {
                    try {
                        $this->deleteDnsRecord($record['id'], $configuration['digitalOceanDomain'], $configuration['apiToken']);
                        $logger->debug(t('Record with ID %s has been deleted.', $record['id']));
                    } catch (RuntimeException $x) {
                        $logger->debug(t('Failed to delete the record with ID %s: %s', $record['id'], $x->getMessage()));
                    }
                }

                return;
            }
        }
        $logger->debug(t('Failed to find the DNS record to be deleted.'));
    }

    /**
     * @param string $apiToken
     *
     * @throws \Acme\Exception\RuntimeException
     *
     * @return string[]
     */
    private function getDigitalOceanDomain(Domain $domain, $apiToken)
    {
        $chunks = explode('.', $domain->getPunycode());
        $tryMe = array_pop($chunks);
        $headers = $this->createClientHeaders($apiToken);
        $client = $this->httpClientFactory->getClient();
        while ($chunks !== []) {
            $tryMe = array_pop($chunks) . '.' . $tryMe;
            $response = $client->get("https://api.digitalocean.com/v2/domains/{$tryMe}", $headers);
            switch ($response->statusCode) {
                case 404:
                    break;
                case 200:
                    $data = json_decode($response->body, true);
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
     * @return int the record ID
     */
    private function createDnsRecord($name, $value, $digitalOceanDomain, $apiToken)
    {
        $headers = $this->createClientHeaders($apiToken);
        $response = $this->httpClientFactory->getClient()->post(
            "https://api.digitalocean.com/v2/domains/{$digitalOceanDomain}/records",
            json_encode([
                'type' => 'TXT',
                'name' => $name,
                'data' => $value,
                'ttl' => 30,
            ], JSON_UNESCAPED_SLASHES),
            $headers
        );
        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw new RuntimeException($this->describeErrorResponse($response));
        }
        $data = json_decode($response->body, true);
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
     *
     * @throws \Acme\Exception\RuntimeException
     */
    private function deleteDnsRecord($recordID, $digitalOceanDomain, $apiToken)
    {
        $headers = $this->createClientHeaders($apiToken);
        $response = $this->httpClientFactory->getClient()->delete(
            "https://api.digitalocean.com/v2/domains/{$digitalOceanDomain}/records/{$recordID}",
            $headers
        );
        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw new RuntimeException($this->describeErrorResponse($response));
        }
    }

    /**
     * @param string $apiToken
     *
     * @return array
     */
    private function createClientHeaders($apiToken)
    {
        return [
            'Authorization' => "Bearer {$apiToken}",
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @return string
     */
    private function describeErrorResponse(Response $response)
    {
        $data = json_decode($response->body, true);
        if (is_array($data)) {
            $message = array_get($data, 'message');
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        return "{$response->statusCode} ({$response->reasonPhrase})";
    }

    /**
     * @param string $punycodeDomain
     * @param string $recordName
     * @param string $recordValue
     *
     * @return bool
     */
    private function waitDnsReady($punycodeDomain, $recordName, $recordValue, LoggerInterface $logger)
    {
        $logger->debug(t('Waiting for the DNS record to be available'));
        $startTime = microtime(true);
        for (;;) {
            if (in_array($recordValue, $this->dnsChecker->listTXTRecords($punycodeDomain, $recordName, $this->queryAuthoritativeNameservers, $logger))) {
                $logger->debug(t('The DNS record has been found'));

                return true;
            }
            $elapsed = microtime(true) - $startTime;
            if ($elapsed > $this->maxDnsWaitSeconds) {
                $logger->debug(t("The DNS record hasn't been found after %s seconds: let's proceed anyway.", $this->maxDnsWaitSeconds));

                return false;
            }
            $logger->debug(t("The DNS record hasn't been found: let's wait for a while.", $this->maxDnsWaitSeconds));
            usleep(500000);
        }
    }
}
