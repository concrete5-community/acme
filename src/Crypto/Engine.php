<?php

namespace Acme\Crypto;

use Acme\Exception\RuntimeException;

/**
 * Get info about the cryptographic engine to be used.
 */
final class Engine
{
    const PHPSECLIB2 = 1;

    const PHPSECLIB3 = 2;

    /**
     * @var int|null
     */
    private static $identifier;

    /**
     * Get the identifier of the cryptographic engine to be used.
     *
     * @throws \Acme\Exception\RuntimeException if no known cryptographic engine is available
     *
     * @return int One of the constants of this class
     */
    public static function get()
    {
        if (empty(self::$identifier)) {
            if (class_exists('phpseclib3\Crypt\RSA')) {
                self::$identifier = self::PHPSECLIB3;
            } elseif (class_exists('phpseclib\Crypt\RSA')) {
                self::$identifier = self::PHPSECLIB2;
            } else {
                throw new RuntimeException(t('No supported cryptographic engine detected.'));
            }
        }

        return self::$identifier;
    }
}
