<?php

namespace Acme\Security;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represent a private/public key pair.
 */
class KeyPair
{
    /**
     * The private key.
     *
     * @var string
     */
    private $privateKey;

    /**
     * The public key.
     *
     * @var string
     */
    private $publicKey;

    protected function __construct()
    {
    }

    /**
     * Create a new instance.
     *
     * @param string $privateKey
     * @param string $publicKey
     *
     * @return static
     */
    public static function create($privateKey, $publicKey)
    {
        $result = new static();
        $result->privateKey = (string) $privateKey;
        $result->publicKey = (string) $publicKey;

        return $result;
    }

    /**
     * Get the private key.
     *
     * @return string
     */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    /**
     * Set the private key.
     *
     * @return string
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }
}
