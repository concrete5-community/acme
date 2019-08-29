<?php

namespace Acme\Exception\Communication;

use Acme\Exception\Exception as ExceptionBase;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Base class for all the exceptions related to the comunication to/from an ACME server.
 */
abstract class Exception extends ExceptionBase
{
}
