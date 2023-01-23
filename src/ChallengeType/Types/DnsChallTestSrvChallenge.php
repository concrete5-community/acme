<?php

namespace Acme\ChallengeType\Types;

use Acme\Entity\AuthorizationChallenge;
use Acme\Entity\Domain;
use Acme\Exception\RuntimeException;
use Acme\Http\ClientFactory as HttpClientFactory;
use ArrayAccess;
use Concrete\Core\Filesystem\ElementManager;
use Concrete\Core\Page\Page;

defined('C5_EXECUTE') or die('Access Denied.');

final class DnsChallTestSrvChallenge extends DnsChallenge
{
    /**
     * @var \Acme\Http\ClientFactory
     */
    private $httpClientFactory;

    /**
     * @var string
     */
    private $handle;

    /**
     * @var string
     */
    private $defaultManagementAddress;

    public function __construct(HttpClientFactory $httpClientFactory)
    {
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
        $this->defaultManagementAddress = (string) $challengeTypeOptions['default_management_address'];
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
        return t('challtestsrv TEST DNS Server');
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
            'managementaddress' => [
                'description' => t('The URL of the management'),
                'defaultValue' => $this->defaultManagementAddress,
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
            'managementaddress' => rtrim((string) array_get($challengeConfiguration, 'managementaddress'), '/'),
        ];
        if ($result['managementaddress'] === '') {
            $result['managementaddress'] = $this->defaultManagementAddress;
        } else {
            if (
                filter_var($result['managementaddress'], FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED) === false
                || !preg_match('_^https?://_i', $result['managementaddress'])
            ) {
                $errors[] = t('The URL of the management seems wrong');
                $failed = true;
            } elseif (strpos($result['managementaddress'], '?') !== false || strpos($result['managementaddress'], '#')) {
                $errors[] = t("The URL of the management can't contain these characters: %s", '"?", "#"');
                $failed = true;
            }
        }
        if (!$failed) {
            try {
                $this->createDnsTokenTxt($domain, 'test', $result['managementaddress']);
                $this->clearDnsTokenTxt($domain, $result['managementaddress']);
            } catch (RuntimeException $x) {
                $errors[] = t('Failed to communicate with the fake DNS Server: %s', $x->getMessage());
                $failed = true;
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
        $managementAddress = $this->defaultManagementAddress;
        if ($domain->getChallengeTypeHandle() === $this->getHandle()) {
            $savedConfiguration = $domain->getChallengeTypeConfiguration();
            if (!empty($savedConfiguration['managementaddress'])) {
                $managementAddress = $savedConfiguration['managementaddress'];
            }
        }

        return $elementManager->get(
            'challenge_type/' . $this->getHandle(),
            'acme',
            $page,
            [
                'defaultManagementAddress' => $this->defaultManagementAddress,
                'managementAddress' => $managementAddress,
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
        $managementAddress = array_get($authorizationChallenge->getDomain()->getChallengeTypeConfiguration(), 'managementaddress');
        $this->createDnsTokenTxt($authorizationChallenge->getDomain(), $authorizationChallenge->getChallengeAuthorizationKey(), $managementAddress);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::afterChallenge()
     */
    public function afterChallenge(AuthorizationChallenge $authorizationChallenge)
    {
        $managementAddress = array_get($authorizationChallenge->getDomain()->getChallengeTypeConfiguration(), 'managementaddress');
        $this->clearDnsTokenTxt($authorizationChallenge->getDomain(), $managementAddress);
    }

    /**
     * @param string $authorizationKey
     * @param string $managementAddress
     *
     * @throws \Acme\Exception\RuntimeException
     */
    private function createDnsTokenTxt(Domain $domain, $authorizationKey, $managementAddress)
    {
        $this->post(
            [
                'host' => '_acme-challenge.' . $domain->getPunycode() . '.',
                'addresses' => ['127.0.0.1'],
            ],
            $managementAddress,
            'add-a'
        );
        $this->post(
            [
                'host' => '_acme-challenge.' . $domain->getPunycode() . '.',
                'value' => $this->generateDnsRecordValue($authorizationKey),
            ],
            $managementAddress,
            'set-txt'
        );
    }

    /**
     * @param string $managementAddress
     *
     * @throws \Acme\Exception\RuntimeException
     */
    private function clearDnsTokenTxt(Domain $domain, $managementAddress)
    {
        $this->post(
            [
                'host' => '_acme-challenge.' . $domain->getPunycode() . '.',
            ],
            $managementAddress,
            'clear-txt'
        );
    }

    /**
     * @param array $data
     * @param string $managementAddress
     * @param string $path
     *
     * @throws \Acme\Exception\RuntimeException
     */
    private function post(array $data, $managementAddress, $path)
    {
        $client = $this->httpClientFactory->getClient(true);
        $response = $client->post(
            $managementAddress . '/' . ltrim($path, '/'),
            json_encode($data, JSON_UNESCAPED_SLASHES)
        );
        if ($response->statusCode !== 200) {
            throw new RuntimeException(t('Error thrown by the fake DNS Server: %s', "{$response->statusCode} ({$response->reasonPhrase})"));
        }
    }
}
