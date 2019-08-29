<?php

namespace Acme\Exception\Codec;

use Acme\Exception\Exception as ExceptionBase;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Base class for all the exceptions related to encoding/decoding variables.
 */
abstract class Exception extends ExceptionBase
{
    /**
     * The variable that failed to be encoded/decoded.
     *
     * @var mixed
     */
    protected $variable;

    /**
     * Get the variable that failed to be encoded/decoded.
     *
     * @return mixed
     */
    public function getVariable()
    {
        return $this->variable;
    }
}
