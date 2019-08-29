<?php

namespace Acme\Exception;

use Exception as ExceptionBase;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Exception thrown when an entity could not be found.
 */
class EntityNotFoundException extends ExceptionBase
{
}
