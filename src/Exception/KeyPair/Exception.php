<?php

namespace Acme\Exception\KeyPair;

use Acme\Exception\Exception as ExceptionBase;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Base class for all the exceptions related to private/public key pairs.
 */
abstract class Exception extends ExceptionBase
{
}
