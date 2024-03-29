<?php

namespace Acme\Service;

defined('C5_EXECUTE') or die('Access Denied.');

trait NotificationSilencerTrait
{
    /**
     * Call a callable without raising PHP notices/warnings.
     *
     * @return mixed the result of $callable
     */
    protected function ignoringWarnings(callable $callable)
    {
        set_error_handler(static function () {}, -1);
        try {
            return $callable();
        } finally {
            restore_error_handler();
        }
    }
}
