<?php

namespace Acme\Server;

use Acme\Entity\Server;
use Acme\Exception\RuntimeException;
use Acme\Http\ClientFactory;
use Acme\Protocol\Version;
use Acme\Service\NotificationSilencerTrait;
use Zend\Http\Client\Exception\RuntimeException as ZendClientRuntimeException;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Class to generate DirectoryInfo instances, extracting data from the contents of an ACME server directory URL.
 */
class DirectoryInfoService
{
    use NotificationSilencerTrait;

    /**
     * @var \Acme\Http\ClientFactory
     */
    protected $httpClientFactory;

    /**
     * @param \Acme\Http\ClientFactory $httpClientFactory
     */
    public function __construct(ClientFactory $httpClientFactory)
    {
        $this->httpClientFactory = $httpClientFactory;
    }

    /**
     * Get a DirectoryInfo instance reading the directory URL of an ACME server instance.
     *
     * @param \Acme\Entity\Server $server
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Server\DirectoryInfo
     */
    public function getInfoFromServer(Server $server)
    {
        return $this->getInfoFromUrl($server->getDirectoryUrl(), $server->isAllowUnsafeConnections());
    }

    /**
     * Get a DirectoryInfo instance reading a directory URL.
     *
     * @param string $directoryUrl
     * @param bool $allowUnsafeConnections
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Server\DirectoryInfo
     */
    public function getInfoFromUrl($directoryUrl, $allowUnsafeConnections = false)
    {
        $httpClient = $this->httpClientFactory->getClient($allowUnsafeConnections);
        try {
            $response = $httpClient->setMethod('GET')->setUri($directoryUrl)->send();
        } catch (ZendClientRuntimeException $x) {
            throw new RuntimeException(t('Failed to retrieve the contents of the ACME Server directory URL: %s', $x->getMessage()));
        }
        if (!$response->isOk()) {
            throw new RuntimeException(t('Failed to retrieve the contents of the ACME Server directory URL: %s', $response->getReasonPhrase() . '(' . $response->getStatusCode() . ')'));
        }
        $data = $this->ignoringWarnings(function () use ($response) {
            return json_decode($response->getBody(), true);
        });
        if (!is_array($data)) {
            throw new RuntimeException(t("The directory URL of the ACME Server doesn't seems correct (it doesn't contain an array in JSON format)."));
        }

        return $this->getInfoFromArray($directoryUrl, $data);
    }

    /**
     * Get a DirectoryInfo instance reading an array.
     *
     * @param string $directoryUrl
     * @param array $data
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Server\DirectoryInfo
     */
    protected function getInfoFromArray($directoryUrl, array $data)
    {
        if (true
            && $this->nonEmptyString(array_get($data, 'newNonce'))
            && $this->nonEmptyString(array_get($data, 'newAccount'))
            && $this->nonEmptyString(array_get($data, 'newOrder'))
            && $this->nonEmptyString(array_get($data, 'revokeCert'))
        ) {
            return $this->getInfoForAcme02($directoryUrl, $data);
        }

        if (true
            && $this->nonEmptyString(array_get($data, 'new-reg'))
            && $this->nonEmptyString(array_get($data, 'new-authz'))
            && $this->nonEmptyString(array_get($data, 'new-cert'))
            && $this->nonEmptyString(array_get($data, 'revoke-cert'))
        ) {
            return $this->getInfoForAcme01($directoryUrl, $data);
        }

        throw new RuntimeException(t('Failed to recognize the data provided by the directory URL of the ACME server'));
    }

    /**
     * Generate a DirectoryInfo instance for the contents of an ACME v2 directory URL.
     *
     * @param string $directoryUrl
     * @param array $data
     *
     * @return \Acme\Server\DirectoryInfo
     */
    protected function getInfoForAcme02($directoryUrl, array $data)
    {
        return DirectoryInfo::create()
            ->setProtocolVersion(Version::ACME_02)
            ->setNewNonceUrl($data['newNonce'])
            ->setNewAccountUrl($data['newAccount'])
            ->setNewAuthorizationUrl(array_get($data, 'newAuthz'))
            ->setNewOrderUrl($data['newOrder'])
            ->setRevokeCertificateUrl($data['revokeCert'])
            ->setTermsOfServiceUrl(array_get($data, 'meta.termsOfService'))
            ->setWebsite(array_get($data, 'meta.website'))
        ;
    }

    /**
     * Generate a DirectoryInfo instance for the contents of an ACME v2 directory URL.
     *
     * @param string $directoryUrl
     * @param array $data
     *
     * @return \Acme\Server\DirectoryInfo
     */
    protected function getInfoForAcme01($directoryUrl, array $data)
    {
        return DirectoryInfo::create()
            ->setProtocolVersion(Version::ACME_01)
            ->setNewNonceUrl($directoryUrl)
            ->setNewAccountUrl(array_get($data, 'new-reg'))
            ->setNewAuthorizationUrl(array_get($data, 'new-authz'))
            ->setNewCertificateUrl(array_get($data, 'new-cert'))
            ->setRevokeCertificateUrl(array_get($data, 'revoke-cert'))
            ->setTermsOfServiceUrl(array_get($data, 'meta.terms-of-service'))
            ->setWebsite(array_get($data, 'meta.website'))
        ;
    }

    /**
     * Check if variable contains a non empty string.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function nonEmptyString($value)
    {
        return is_string($value) && $value !== '';
    }
}
