<?php

namespace Acme\Exception\KeyPair;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Exception thrown when a private key is not valid.
 */
class MalformedPrivateKeyException extends Exception
{
    /**
     * The invalid private key.
     *
     * @var string
     */
    private $invalidPrivateKey;

    /**
     * Create a new instance.
     *
     * @param string $wrongPrivateKey The invalid private key
     * @param mixed $invalidPrivateKey
     *
     * @return static
     */
    public static function create($invalidPrivateKey)
    {
        $result = new static(t('The private key is not valid'));
        $result->invalidPrivateKey = is_string($invalidPrivateKey) ? $invalidPrivateKey : '';

        return $result;
    }

    /**
     * Get the invalid private key.
     *
     * @return string
     */
    public function getInvalidPrivateKey()
    {
        return $this->invalidPrivateKey;
    }
}
