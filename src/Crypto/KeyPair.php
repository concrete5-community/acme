<?php

namespace Acme\Crypto;

use Exception;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represent a private/public key pair.
 */
final class KeyPair
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

    /**
     * @var \Acme\Crypto\PrivateKey|null
     */
    private $privateKeyObject;

    /**
     * @var int
     */
    private $engineID;

    /**
     * @param string $privateKey
     * @param string $publicKey
     * @param int|null $engineID The value of one of the Acme\Crypto\Engine constants
     */
    public function __construct($privateKey, $publicKey, $engineID = null)
    {
        $this->privateKey = (string) $privateKey;
        $this->publicKey = (string) $publicKey;
        $this->engineID = $engineID === null ? Engine::get() : $engineID;
    }

    /**
     * Create a new KeyPair instance from a string containing a private key.
     *
     * @param string|mixed $privateKeyString
     * @param int|null $engineID The value of one of the Acme\Crypto\Engine constants
     *
     * @return \Acme\Crypto\KeyPair|null return NULL if $privateKeyString is not a string, or if it's not a private key
     */
    public static function fromPrivateKeyString($privateKeyString, $engineID = null)
    {
        if (!is_string($privateKeyString) || $privateKeyString === '') {
            return null;
        }
        if ($engineID === null) {
            $engineID = Engine::get();
        }
        try {
            $privateKey = PrivateKey::fromString($privateKeyString, $engineID);
        } catch (Exception $x) {
            return null;
        }

        return self::fromPrivateKeyObject($privateKey, $engineID);
    }

    /**
     * @param int $engineID The value of one of the Acme\Crypto\Engine constants
     *
     * @return \Acme\Crypto\KeyPair
     */
    public static function fromPrivateKeyObject(PrivateKey $privateKey, $engineID)
    {
        $privateKeyString = $privateKey->getPrivateKeyString();
        $publicKeyString = $privateKey->getPublicKeyString();
        $result = new self($privateKeyString, $publicKeyString, $engineID);
        $result->privateKeyObject = $privateKey;

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

    /**
     * @throws \Acme\Exception\RuntimeException if the private key string is malformed
     *
     * @return \Acme\Crypto\PrivateKey
     */
    public function getPrivateKeyObject()
    {
        if ($this->privateKeyObject === null) {
            $this->privateKeyObject = PrivateKey::fromString($this->privateKey, $this->engineID);
        }

        return $this->privateKeyObject;
    }

    /**
     * Get the size (in bits) of a key.
     *
     * @return int|null return NULL if the private key string is malformed
     */
    public function getPrivateKeySize()
    {
        try {
            return $this->getPrivateKeyObject()->getSize();
        } catch (Exception $x) {
            return null;
        }
    }
}
