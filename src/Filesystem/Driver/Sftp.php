<?php

namespace Acme\Filesystem\Driver;

use Acme\Crypto\Engine;
use Acme\Entity\RemoteServer;
use Acme\Exception\FilesystemException;
use Acme\Exception\RuntimeException;
use Acme\Filesystem\DriverInterface;
use Acme\Filesystem\ExecutableDriverInterface;
use Acme\Filesystem\RemoteDriverInterface;
use Concrete\Core\Foundation\Environment\FunctionInspector;
use Exception;
use phpseclib\Net\SFTP as phpseclibSftp2;
use phpseclib3\Net\SFTP as phpseclibSftp3;
use Throwable;

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
     * @var \phpseclib\Net\SFTP|\phpseclib3\Net\SFTP|null
     */
    protected $connectionResource;

    /**
     * @var int
     */
    protected $engineID;

    /**
     * @param string $handle
     * @param int $defaultPort
     * @param int $defaultConnectionTimeout
     * @param int|null $engineID The value of one of the Acme\Crypto\Engine constants
     */
    protected function __construct($handle, $defaultPort, $defaultConnectionTimeout, $engineID = null)
    {
        $this->handle = $handle;
        $this->defaultPort = $defaultPort;
        $this->defaultConnectionTimeout = $defaultConnectionTimeout;
        $this->engineID = $engineID === null ? Engine::get() : $engineID;
    }

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
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                return $this->connectionResource->is_file($path);
            default:
                throw new RuntimeException('Not implemented');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::isDirectory()
     */
    public function isDirectory($path)
    {
        $this->connect();
        switch (Engine::get()) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                return $this->connectionResource->is_dir($path);
            default:
                throw new RuntimeException('Not implemented');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::getFileContents()
     */
    public function getFileContents($path)
    {
        $this->connect();
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                $result = $this->connectionResource->get($path);
                if ($result === false) {
                    throw FilesystemException::create(FilesystemException::ERROR_READING_FILE, t('Failed to download file %s via SFTP', $path), $path);
                }

                return $result;
            default:
                throw new RuntimeException('Not implemented');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::setFileContents()
     */
    public function setFileContents($path, $contents)
    {
        $this->connect();
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                if (!$this->connectionResource->put($path, $contents)) {
                    throw FilesystemException::create(FilesystemException::ERROR_WRITING_FILE, t('Failed to upload file %s via SFTP', $path), $path);
                }
                break;
            default:
                throw new RuntimeException('Not implemented');
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
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                if (!$this->connectionResource->chmod($mode, $path)) {
                    throw FilesystemException::create(FilesystemException::ERROR_SETTING_PERMISSIONS, t('Failed to set the permissions of %s via SFTP', $path), $path);
                }
                break;
            default:
                throw new RuntimeException('Not implemented');
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
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                if (!$this->connectionResource->mkdir($path, $mode)) {
                    throw FilesystemException::create(FilesystemException::ERROR_CREATING_DIRECTORY, t('Failed to create the directory %s via SFTP', $path), $path);
                }
                break;
            default:
                throw new RuntimeException('Not implemented');
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
            switch ($this->engineID) {
                case Engine::PHPSECLIB2:
                case Engine::PHPSECLIB3:
                    if (!$this->connectionResource->delete($path, false)) {
                        $failed[] = $path;
                    }
                    break;
                default:
                    throw new RuntimeException('Not implemented');
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
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                if (!$this->connectionResource->rmdir($path)) {
                    throw FilesystemException::create(FilesystemException::ERROR_DELETING_DIRECTORY, t('Failed to delete the directory %s via SFTP', $path), $path);
                }
                break;
            default:
                throw new RuntimeException('Not implemented');
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
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                return (bool) $this->connectionResource->rmdir($path);
            default:
                throw new RuntimeException('Not implemented');
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\ExecutableDriverInterface::executeCommand()
     */
    public function executeCommand($command, &$output = '')
    {
        $output = '';
        $rc = null;
        $this->connect();
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                $outputParts = [];
                $oldQuietMode = $this->connectionResource->isQuietModeEnabled();
                $this->connectionResource->enableQuietMode();
                try {
                    $execResult = $this->connectionResource->exec($command);
                } finally {
                    if (!$oldQuietMode) {
                        @$this->connectionResource->disableQuietMode();
                    }
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
                $se = $this->connectionResource->getStdError();
                if (is_string($se)) {
                    $se = trim($se);
                    if ($se !== '') {
                        $outputParts[] = $se;
                    }
                }
                $output = implode("\n", $outputParts);
                $rc = $this->connectionResource->getExitStatus();
                break;
            default:
                throw new RuntimeException('Not implemented');
        }

        return is_numeric($rc) ? (int) $rc : -1;
    }

    /**
     * Open the connection (if not already open).
     *
     * @throws \Acme\Exception\Exception
     */
    protected function connect()
    {
        if ($this->remoteServer === null) {
            throw FilesystemException::create(FilesystemException::ERROR_CONNECTING_NOSERVER, t('The remote server has not been specified'));
        }
        if ($this->connectionResource !== null) {
            switch ($this->engineID) {
                case Engine::PHPSECLIB2:
                case Engine::PHPSECLIB3:
                    if ($this->connectionResource->isConnected()) {
                        return;
                    }
                    break;
                default:
                    throw new RuntimeException('Not implemented');
            }
            $this->disconnect();
        }
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
                $connectionResource = new phpseclibSftp2(
                    $this->remoteServer->getHostname(),
                    $this->remoteServer->getPort() ?: $this->defaultPort,
                    $this->remoteServer->getConnectionTimeout() ?: $this->defaultConnectionTimeout
                );
                break;
            case Engine::PHPSECLIB3:
                $connectionResource = new phpseclibSftp3(
                    $this->remoteServer->getHostname(),
                    $this->remoteServer->getPort() ?: $this->defaultPort,
                    $this->remoteServer->getConnectionTimeout() ?: $this->defaultConnectionTimeout
                );
                break;
            default:
                throw new RuntimeException('Not implemented');
        }
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                try {
                    $loggedIn = $connectionResource->login($this->remoteServer->getUsername(), $this->getLoginParameter());
                } catch (Exception $x) {
                    $loggedIn = false;
                } catch (Throwable $x) {
                    $loggedIn = false;
                }
                if (!$loggedIn) {
                    try {
                        $connectionResource->disconnect();
                    } catch (Exception $foo) {
                    } catch (Throwable $foo) {
                    }
                    throw FilesystemException::create(FilesystemException::ERROR_CONNECTING, t('Failed to access to the SSH2 server with the specified login options'));
                }
                break;
            default:
                throw new RuntimeException('Not implemented');
        }
        $this->connectionResource = $connectionResource;
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
        if ($this->connectionResource === null) {
            return;
        }
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                $connectionResource = $this->connectionResource;
                $this->connectionResource = null;
                $connectionResource->disconnect();
                break;
            default:
                throw new RuntimeException('Not implemented');
        }
    }
}
