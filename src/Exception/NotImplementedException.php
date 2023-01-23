<?php

namespace Acme\Exception;

use RuntimeException as SplRuntimeException;

defined('C5_EXECUTE') or die('Access Denied.');

class NotImplementedException extends SplRuntimeException
{
    public function __construct()
    {
        parent::__construct('Not implemented');
    }
}
