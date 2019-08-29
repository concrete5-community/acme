<?php

namespace Acme\Exception;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Exception thrown by the download() method of the Acme\Security\FileDownloader class.
 */
class FileDownloaderException extends Exception
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
