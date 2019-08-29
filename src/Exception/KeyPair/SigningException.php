<?php

namespace Acme\Exception\KeyPair;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Exception thrown when digitally signing failed.
 */
class SigningException extends Exception
{
    /**
     * Create a new instance.
     *
     * @param string $message the exception message
     *
     * @return static
     */
    public static function create($message)
    {
        $result = new static($message);

        return $result;
    }
}
