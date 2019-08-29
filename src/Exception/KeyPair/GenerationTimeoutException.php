<?php

namespace Acme\Exception\KeyPair;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Exception thrown when the generation of a private key timed out.
 */
class GenerationTimeoutException extends Exception
{
    /**
     * The size of the private key that we weren't able to generate.
     *
     * @var int
     */
    private $wantedSize;

    /**
     * Create a new instance.
     *
     * @param int $wantedSize the size of the private key that we weren't able to generate
     *
     * @return static
     */
    public static function create($wantedSize)
    {
        if (extension_loaded('openssl')) {
            $message = t('Timeout generating the new private key of %s bits: try to lower its size', $wantedSize);
        } else {
            $message = t('Timeout generating the new private key of %s bits: try to lower its size or to enable the OpenSSL PHP extension', $wantedSize);
        }

        $result = new static($message);
        $result->wantedSize = (int) $wantedSize;

        return $result;
    }

    /**
     * Get the size of the private key that we weren't able to generate.
     *
     * @return int
     */
    public function getWantedSize()
    {
        return $this->wantedSize;
    }
}
