<?php

namespace Acme\Exception;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Exception thrown when a file system operation fails.
 */
class FilesystemException extends Exception
{
    /**
     * Failed to read the contents of a file.
     *
     * @var int
     */
    const ERROR_READING_FILE = 1;

    /**
     * Failed to write the contents of a file.
     *
     * @var int
     */
    const ERROR_WRITING_FILE = 2;

    /**
     * Failed to call chmod on a file/directory.
     *
     * @var int
     */
    const ERROR_SETTING_PERMISSIONS = 3;

    /**
     * Failed to create a directory.
     *
     * @var int
     */
    const ERROR_CREATING_DIRECTORY = 4;

    /**
     * Failed to delete a file.
     *
     * @var int
     */
    const ERROR_DELETING_FILE = 5;

    /**
     * Failed to delete a directory.
     *
     * @var int
     */
    const ERROR_DELETING_DIRECTORY = 6;

    /**
     * The remote server to be connected to is not specified.
     *
     * @var int
     */
    const ERROR_CONNECTING_NOSERVER = 7;

    /**
     * Failed to connecto to the remote server.
     *
     * @var int
     */
    const ERROR_CONNECTING = 8;

    /**
     * The execution of local commands is disabled.
     *
     * @var int
     */
    const ERROR_EXEC_DISABLED = 9;

    /**
     * General error.
     *
     * @var int
     */
    const ERROR_GENERAL = 10;

    /**
     * @var mixed
     */
    protected $arguments;

    /**
     * Create a new instance.
     *
     * @param int $code One of the ERRORCODE_... constants
     * @param string $message the description of the error
     * @param mixed $arguments the arguments/subject of the operation that failed
     *
     * @return static
     */
    public static function create($code, $message, $arguments = null)
    {
        $result = new static($message, $code);
        $result->arguments = $arguments;

        return $result;
    }

    /**
     * The arguments/subject of the operation that failed.
     *
     * @return mixed
     */
    public function getArguments()
    {
        return $this->arguments;
    }
}
