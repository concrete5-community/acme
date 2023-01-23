<?php

namespace Acme\Filesystem;

use Concrete\Core\Foundation\Environment\FunctionInspector;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * The interface that file system drivers must implement.
 */
interface DriverInterface
{
    /**
     * Get the name of this driver.
     *
     * @return string
     */
    public static function getName(array $options);

    /**
     * Check whether this driver can be used.
     *
     * @return bool
     */
    public static function isAvailable(array $options, FunctionInspector $functionInspector);

    /**
     * Create a new instance of the driver.
     *
     * @param string $handle
     *
     * @return static
     */
    public static function create($handle, array $options);

    /**
     * Get the handle of this driver.
     *
     * @return string
     */
    public function getHandle();

    /**
     * Determine if the given path is a file.
     *
     * @param string $path
     *
     * @throws \Acme\Exception\FilesystemException
     *
     * @return bool
     */
    public function isFile($path);

    /**
     * Determine if the given path is a directory.
     *
     * @param string $path
     *
     * @throws \Acme\Exception\FilesystemException
     *
     * @return bool
     */
    public function isDirectory($path);

    /**
     * Get the contents of a file.
     *
     * @param string $path
     *
     * @throws \Acme\Exception\FilesystemException
     *
     * @return string
     */
    public function getFileContents($path);

    /**
     * Write the contents of a file.
     *
     * @param string $path
     * @param string $contents
     *
     * @throws \Acme\Exception\FilesystemException
     */
    public function setFileContents($path, $contents);

    /**
     * Changes file mode.
     *
     * @param string $path path to the file/directory
     * @param int $mode new file mode
     *
     * @throws \Acme\Exception\FilesystemException
     */
    public function chmod($path, $mode);

    /**
     * Create a directory.
     *
     * @param string $path
     * @param int $mode
     *
     * @throws \Acme\Exception\FilesystemException
     */
    public function createDirectory($path, $mode = 0777);

    /**
     * Attempt to delete the file(s) at a given path.
     *
     * @param string|string[] $paths
     *
     * @throws \Acme\Exception\FilesystemException
     */
    public function deleteFile($paths);

    /**
     * Attempt to remove an empty directory.
     *
     * @param string $path
     *
     * @throws \Acme\Exception\FilesystemException
     */
    public function deleteEmptyDirectory($path);

    /**
     * Delete a directory only if't empty.
     *
     * @param string $path
     *
     * @throws \Acme\Exception\FilesystemException
     *
     * @return bool TRUE if deleted
     */
    public function deleteDirectoryIfEmpty($path);
}
