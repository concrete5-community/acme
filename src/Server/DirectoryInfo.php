<?php

namespace Acme\Server;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents the data presented in an ACME server directory URL.
 */
final class DirectoryInfo
{
    use DirectoryInfoTrait;

    /**
     * The ACME protocol version (one of the Version::... constants).
     *
     * @var string
     *
     * @see \Acme\Protocol\Version
     */
    private $protocolVersion;

    /**
     * The URL to be called to generate new nonces.
     *
     * @var string
     */
    private $newNonceUrl;

    /**
     * The URL to be called to register new accounts.
     *
     * @var string
     */
    private $newAccountUrl;

    /**
     * The URL to be called to authorize domains (always in ACME v1, optional in ACME v2).
     *
     * @var string
     */
    private $newAuthorizationUrl;

    /**
     * The URL to be called to request new certificates (only in ACME v1).
     *
     * @var string
     */
    private $newCertificateUrl;

    /**
     * The URL to be called to request new certificates (only in ACME v2).
     *
     * @var string
     */
    private $newOrderUrl;

    /**
     * The URL to be called to revoce certificates.
     *
     * @var string
     */
    private $revokeCertificateUrl;

    /**
     * The URL to be called to read/accept the ACME server terms of service (optional).
     *
     * @var string
     */
    private $termsOfServiceUrl;

    /**
     * The URL of the ACME service provider website (optional).
     *
     * @var string
     */
    private $website;

    private function __construct()
    {
    }

    /**
     * Create a new instance.
     *
     * @return static
     */
    public static function create()
    {
        $result = new static();

        return $result->resetDirectoryInfo();
    }
}
