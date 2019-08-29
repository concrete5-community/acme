<?php

namespace Acme\Exception;

use Exception as ExceptionBase;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Base class for all the exceptions thrown in the ACME package.
 */
abstract class Exception extends ExceptionBase
{
}
