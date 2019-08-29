<?php

namespace Acme\Server;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper trait for classes containing the data presented in an ACME server directory URL.
 */
trait DirectoryInfoTrait
{
    /**
     * Get the ACME protocol version (one of the Version::... constants).
     *
     * @return string
     *
     * @see \Acme\Protocol\Version
     */
    public function getProtocolVersion()
    {
        return $this->protocolVersion;
    }

    /**
     * Set the ACME protocol version (one of the Version::... constants).
     *
     * @param string $value
     *
     * @return $this
     *
     * @see \Acme\Protocol\Version
     */
    public function setProtocolVersion($value)
    {
        $this->protocolVersion = (string) $value;

        return $this;
    }

    /**
     * Get the URL to be called to generate new nonces.
     *
     * @return string
     */
    public function getNewNonceUrl()
    {
        return $this->newNonceUrl;
    }

    /**
     * Set the URL to be called to generate new nonces.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setNewNonceUrl($value)
    {
        $this->newNonceUrl = (string) $value;

        return $this;
    }

    /**
     * Get the URL to be called to register new accounts.
     *
     * @return string
     */
    public function getNewAccountUrl()
    {
        return $this->newAccountUrl;
    }

    /**
     * Set the URL to be called to register new accounts.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setNewAccountUrl($value)
    {
        $this->newAccountUrl = (string) $value;

        return $this;
    }

    /**
     * Get the URL to be called to authorize domains (always in ACME v1, optional in ACME v2).
     *
     * @return string
     */
    public function getNewAuthorizationUrl()
    {
        return $this->newAuthorizationUrl;
    }

    /**
     * Set the URL to be called to authorize domains (always in ACME v1, optional in ACME v2).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setNewAuthorizationUrl($value)
    {
        $this->newAuthorizationUrl = (string) $value;

        return $this;
    }

    /**
     * Get the URL to be called to request new certificates (only in ACME v1).
     *
     * @return string
     */
    public function getNewCertificateUrl()
    {
        return $this->newCertificateUrl;
    }

    /**
     * Set the URL to be called to request new certificates (only in ACME v1).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setNewCertificateUrl($value)
    {
        $this->newCertificateUrl = (string) $value;

        return $this;
    }

    /**
     * Get the URL to be called to request new certificates (only in ACME v2).
     *
     * @return string
     */
    public function getNewOrderUrl()
    {
        return $this->newOrderUrl;
    }

    /**
     * Set the URL to be called to request new certificates (only in ACME v2).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setNewOrderUrl($value)
    {
        $this->newOrderUrl = (string) $value;

        return $this;
    }

    /**
     * Get the URL to be called to revoce certificates.
     *
     * @return string
     */
    public function getRevokeCertificateUrl()
    {
        return $this->revokeCertificateUrl;
    }

    /**
     * Set the URL to be called to revoce certificates.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setRevokeCertificateUrl($value)
    {
        $this->revokeCertificateUrl = (string) $value;

        return $this;
    }

    /**
     * Get the URL to be called to read/accept the ACME server terms of service (optional).
     *
     * @return string
     */
    public function getTermsOfServiceUrl()
    {
        return $this->termsOfServiceUrl;
    }

    /**
     * Set the URL to be called to read/accept the ACME server terms of service (optional).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setTermsOfServiceUrl($value)
    {
        $this->termsOfServiceUrl = (string) $value;

        return $this;
    }

    /**
     * Get the URL of the ACME service provider website (optional).
     *
     * @return string
     */
    public function getWebsite()
    {
        return $this->website;
    }

    /**
     * Set the URL of the ACME service provider website (optional).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setWebsite($value)
    {
        $this->website = (string) $value;

        return $this;
    }

    /**
     * Reset all the directory info-related data.
     *
     * @return $this
     */
    protected function resetDirectoryInfo()
    {
        return $this
            ->setProtocolVersion('')
            ->setNewNonceUrl('')
            ->setNewAccountUrl('')
            ->setNewAuthorizationUrl('')
            ->setNewCertificateUrl('')
            ->setNewOrderUrl('')
            ->setRevokeCertificateUrl('')
            ->setTermsOfServiceUrl('')
            ->setWebsite('')
        ;
    }
}
