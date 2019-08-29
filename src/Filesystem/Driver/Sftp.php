<?php

namespace Acme\Filesystem\Driver;

use Acme\Entity\RemoteServer;
use Acme\Exception\FilesystemException;
use Acme\Filesystem\DriverInterface;
use Acme\Filesystem\ExecutableDriverInterface;
use Acme\Filesystem\RemoteDriverInterface;
use Concrete\Core\Foundation\Environment\FunctionInspector;
use phpseclib\Net\SFTP as phpseclibSftp;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Driver to work with remoe filesystems via SFTP.
 */
abstract class Sftp implements DriverInterface, ExecutableDriverInterface, RemoteDriverInterface
{
    /**
     * @var string
     */
    protected $handle;

    /**
     * @var \Acme\Entity\RemoteServer|null
     */
    protected $remoteServer;

    /**
     * @var int
     */
    protected $defaultPort;

    /**
     * @var int
     */
    protected $defaultConnectionTimeout;

    /**
     * @var \phpseclib\Net\SFTP|null
     */
    protected $connection;

    /**
     * @param string $handle
     * @param int $defaultPort
     * @param int $defaultConnectionTimeout
     */
    protected function __construct($handle, $defaultPort, $defaultConnectionTimeout)
    {
        $this->handle = $handle;
        $this->defaultPort = $defaultPort;
        $this->defaultConnectionTimeout = $defaultConnectionTimeout;
    }

    /**
     * Destruct the instance.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::isAvailable()
     */
    public static function isAvailable(array $options, FunctionInspector $functionInspector)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::create()
     */
    public static function create($handle, array $options)
    {
        return new static(
            $handle,
            (int) $options['default_port'],
            (int) $options['default_connection_timeout']
        );
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::getHandle()
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\RemoteDriverInterface::setRemoteServer()
     */
    public function setRemoteServer(RemoteServer $remoteServer)
    {
        if ($remoteServer !== $this->remoteServer) {
            $this->disconnect();
            $this->remoteServer = $remoteServer;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\RemoteDriverInterface::checkConnection()
     */
    public function checkConnection()
    {
        $this->connect();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::isFile()
     */
    public function isFile($path)
    {
        $this->connect();

        return $this->connection->is_file($path);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::isDirectory()
     */
    public function isDirectory($path)
    {
        $this->connect();

        return $this->connection->is_dir($path);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::getFileContents()
     */
    public function getFileContents($path)
    {
        $this->connect();
        $result = $this->connection->get($path);
        if ($result === false) {
            throw FilesystemException::create(FilesystemException::ERROR_READING_FILE, t('Failed to download file %s via SFTP', $path), $path);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::setFileContents()
     */
    public function setFileContents($path, $contents)
    {
        $this->connect();
        if (!$this->connection->put($path, $contents, self::SOURCE_STRING)) {
            throw FilesystemException::create(FilesystemException::ERROR_WRITING_FILE, t('Failed to upload file %s via SFTP', $path), $path);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::chmod()
     */
    public function chmod($path, $mode)
    {
        $this->connect();
        if (!$this->connection->chmod($mode, $path)) {
            throw FilesystemException::create(FilesystemException::ERROR_SETTING_PERMISSIONS, t('Failed to set the permissions of %s via SFTP', $path), $path);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::createDirectory()
     */
    public function createDirectory($path, $mode = 0777)
    {
        $this->connect();
        if (!$this->connection->mkdir($path, $mode)) {
            throw FilesystemException::create(FilesystemException::ERROR_CREATING_DIRECTORY, t('Failed to create the directory %s via SFTP', $path), $path);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::deleteFile()
     */
    public function deleteFile($paths)
    {
        $this->connect();

        $paths = is_array($paths) ? array_values($paths) : [$paths];
        $failed = [];
        foreach ($paths as $path) {
            if (!$this->connection->delete($path, false)) {
                $failed[] = $path;
            }
        }
        switch (count($failed)) {
            case 0:
                break;
            case 1:
                throw FilesystemException::create(FilesystemException::ERROR_DELETING_FILE, t('Failed to delete the file %s via SFTP', $failed[0]), $failed[0]);
            default:
                throw FilesystemException::create(FilesystemException::ERROR_DELETING_FILE, t('Failed to delete the following files via SFTP:') . "\n" . implode("\n", $failed), $failed);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::deleteEmptyDirectory()
     */
    public function deleteEmptyDirectory($path)
    {
        $this->connect();
        if (!$this->connection->rmdir($path)) {
            throw FilesystemException::create(FilesystemException::ERROR_DELETING_DIRECTORY, t('Failed to delete the directory %s via SFTP', $path), $path);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::deleteDirectoryIfEmpty()
     */
    public function deleteDirectoryIfEmpty($path)
    {
        $this->connect();

        return (bool) $this->connection->rmdir($path);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\ExecutableDriverInterface::executeCommand()
     */
    public function executeCommand($command, &$output = '')
    {
        $output = '';
        $outputParts = [];
        $this->connect();

        $oldQuietMode = $this->connection->isQuietModeEnabled();
        $this->connection->enableQuietMode();
        $execResult = $this->connection->exec($command);
        if (!$oldQuietMode) {
            @$this->connection->disableQuietMode();
        }
        if ($execResult === false) {
            return -1;
        }
        if (is_string($execResult)) {
            $execResult = trim($execResult);
            if ($execResult !== '') {
                $outputParts[] = $execResult;
            }
        }
        $se = $this->connection->getStdError();
        if (is_string($se)) {
            $se = trim($se);
            if ($se !== '') {
                $outputParts[] = $se;
            }
        }
        $output = implode("\n", $outputParts);
        $rc = $this->connection->getExitStatus();

        return is_numeric($rc) ? $rc : -1;
    }

    /**
     * Open the connection (if not already open).
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \phpseclib\Net\SFTP
     */
    protected function connect()
    {
        if ($this->remoteServer === null) {
            throw FilesystemException::create(FilesystemException::ERROR_CONNECTING_NOSERVER, t('The remote server has not been specified'));
        }
        if ($this->connection !== null) {
            if ($this->connection->isConnected()) {
                return;
            }
            $this->disconnect();
        }
        $connection = new phpseclibSftp(
            $this->remoteServer->getHostname(),
            $this->remoteServer->getPort() ?: $this->defaultPort,
            $this->remoteServer->getConnectionTimeout() ?: $this->defaultConnectionTimeout
        );
        $loggedIn = false;
        $error = null;
        try {
            $loggedIn = $connection->login($this->remoteServer->getUsername(), $this->getLoginParameter());
        } catch (\Exception $x) {
            $error = $x;
        } catch (\Throwable $x) {
            $error = $x;
        }
        if (!$loggedIn) {
            try {
                $connection->disconnect();
            } catch (\Exception $foo) {
            } catch (\Throwable $foo) {
            }
            if ($error instanceof FilesystemException) {
                throw $error;
            }
            throw FilesystemException::create(FilesystemException::ERROR_CONNECTING, t('Failed to access to the SSH2 server with the specified login options'));
        }
        $this->connection = $connection;
    }

    /**
     * Get the value of the second parameter to be used when logging in.
     *
     * @throws \Acme\Exception\FilesystemException
     *
     * @return mixed
     */
    abstract protected function getLoginParameter();

    /**
     * Close the connection (if it's open).
     */
    protected function disconnect()
    {
        $connection = $this->connection;
        $this->connection = null;
        if ($connection !== null) {
            $connection->disconnect();
        }
    }
}
