<?php

namespace Acme\Exception\KeyPair;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Exception thrown when the size of the private key is too short.
 */
class PrivateKeyTooShortException extends Exception
{
    /**
     * The too-sort size of the private key.
     *
     * @var int
     */
    private $wrongSize;

    /**
     * The minimum size of private keys.
     *
     * @var int
     */
    private $minimumSize;

    /**
     * Create a new instance.
     *
     * @param int $wrongSize the too-sort size of the private key
     * @param int $minimumSize the minimum size of private keys
     *
     * @return static
     */
    public static function create($wrongSize, $minimumSize)
    {
        $result = new static(t('%1$s is too short for private keys (the minimum key size is %2$s bits)', $wrongSize, $minimumSize));
        $result->wrongSize = (int) $wrongSize;
        $result->minimumSize = (int) $minimumSize;

        return $result;
    }

    /**
     * Get the too-sort size of the private key.
     *
     * @return int
     */
    public function getWrongSize()
    {
        return $this->wrongSize;
    }

    /**
     * Get the minimum size of private keys.
     *
     * @return int
     */
    public function getMinimumSize()
    {
        return $this->minimumSize;
    }
}
