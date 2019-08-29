<?php

namespace Acme\Filesystem;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * The interface implemented by file system drivers if they can execute commands.
 */
interface ExecutableDriverInterface extends DriverInterface
{
    /**
     * Execute a command.
     *
     * @param string $command the command to execute
     * @param string $output [output] The output of the command
     *
     * @throws \Acme\Exception\FilesystemException
     *
     * @return int The command return code
     */
    public function executeCommand($command, &$output = '');
}
