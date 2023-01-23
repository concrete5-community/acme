<?php

namespace Acme\Filesystem;

use Acme\Entity\RemoteServer;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * The interface implemented by file system drivers if they connect to remote systems.
 */
interface RemoteDriverInterface extends DriverInterface
{
    /**
     * Login flags: none.
     *
     * @var int
     */
    const LOGINFLAG_NONE = 0b0;

    /**
     * Login flags: the driver uses usernames.
     *
     * @var int
     */
    const LOGINFLAG_USERNAME = 0b1;

    /**
     * Login flags: the driver uses passwords.
     *
     * @var int
     */
    const LOGINFLAG_PASSWORD = 0b10;

    /**
     * Login flags: the driver uses a private key.
     *
     * @var int
     */
    const LOGINFLAG_PRIVATEKEY = 0b100;

    /**
     * Login flags: the driver uses an SSH agent.
     *
     * @var int
     */
    const LOGINFLAG_SSHAGENT = 0b1000;

    /**
     * Set the remote server to work with.
     *
     * @return $this
     */
    public function setRemoteServer(RemoteServer $remoteServer);

    /**
     * Get the fields required/supported for login.
     *
     * @return int One or more of the RemoteDriverInterface::LOGINFLAG_... constants
     */
    public static function getLoginFlags(array $options);

    /**
     * Checks if the connection is valid (throws an Exception if not).
     *
     * @throws \Acme\Exception\FilesystemException
     */
    public function checkConnection();
}
