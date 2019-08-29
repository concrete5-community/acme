<?php

namespace Acme\Filesystem;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * The interface implemented by file system drivers if they can check if a file or a directory is writable.
 */
interface WritableAwareDriverInterface extends DriverInterface
{
    /**
     * Check if a file or a directory is writable.
     *
     * @param string $path
     *
     * @throws \Acme\Exception\Exception
     *
     * @return bool
     */
    public function isWritable($path);
}
