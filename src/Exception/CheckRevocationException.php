<?php

namespace Acme\Exception;

use Exception as ExceptionBase;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Exception thrown when checking a certificate revocation.
 */
class CheckRevocationException extends ExceptionBase
{
}
