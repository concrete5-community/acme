<?php

namespace Acme\Exception\Codec;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Exception thrown when the conversion of a string to base64 failed.
 */
class Base64EncodingException extends Exception
{
    /**
     * Create a new instance.
     *
     * @param mixed $variable the variable that failed to be encoded
     *
     * @return static
     */
    public static function create($variable)
    {
        $result = new static(t('Failed to create the base-64 representation of a variable'));
        $result->variable = $variable;

        return $result;
    }
}
