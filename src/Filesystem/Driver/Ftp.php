<?php

namespace Acme\Filesystem\Driver;

use Acme\Entity\RemoteServer;
use Acme\Exception\FilesystemException;
use Acme\Filesystem\DriverInterface;
use Acme\Filesystem\RemoteDriverInterface;
use Acme\Service\NotificationSilencerTrait;
use Concrete\Core\Foundation\Environment\FunctionInspector;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Driver to work with remoe filesystems via FTP.
 */
class Ftp implements DriverInterface, RemoteDriverInterface
{
    use NotificationSilencerTrait;

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
     * @var bool
     */
    protected $passive;

    /**
     * @var bool
     */
    protected $ssl;

    /**
     * @var resource|null
     */
    protected $connection;

    /**
     * @param string $handle
     * @param int $defaultPort
     * @param int $defaultConnectionTimeout
     * @param bool $passive
     * @param bool $ssl
     */
    protected function __construct($handle, $defaultPort, $defaultConnectionTimeout, $passive, $ssl)
    {
        $this->handle = $handle;
        $this->defaultPort = $defaultPort;
        $this->defaultConnectionTimeout = $defaultConnectionTimeout;
        $this->passive = $passive;
        $this->ssl = $ssl;
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
     * @see \Acme\Filesystem\DriverInterface::getName()
     */
    public static function getName(array $options)
    {
        if (empty($options['passive'])) {
            return empty($options['ssl']) ? t('FTP') : t('FTP over SSL');
        }

        return empty($options['ssl']) ? t('FTP (passive)') : t('FTP over SSL (passive)');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::isAvailable()
     */
    public static function isAvailable(array $options, FunctionInspector $functionInspector)
    {
        if (!$functionInspector->functionAvailable('ftp_login')) {
            return false;
        }

        return empty($options['ssl']) ? $functionInspector->functionAvailable('ftp_connect') : $functionInspector->functionAvailable('ftp_ssl_connect');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\RemoteDriverInterface::getLoginFlags()
     */
    public static function getLoginFlags(array $options)
    {
        return static::LOGINFLAG_USERNAME | static::LOGINFLAG_PASSWORD;
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
            (int) $options['default_connection_timeout'],
            !empty($options['passive']),
            !empty($options['ssl'])
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
        $this->ignoringWarnings(function () {
            if (ftp_pwd($this->connection) === false) {
                throw FilesystemException::create(FilesystemException::ERROR_CONNECTING, t('Failed to determine the current FTP directory'));
            }
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::isFile()
     */
    public function isFile($path)
    {
        $this->connect();

        return $this->ignoringWarnings(function () use ($path) {
            $size = ftp_size($this->connection, $path);
            if (!is_numeric($size)) {
                return false;
            }
            $size = (int) $size;
            if ($size === 0) {
                return !$this->isDirectory($path);
            }

            return $size > 0;
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::isDirectory()
     */
    public function isDirectory($path)
    {
        $this->connect();

        return $this->ignoringWarnings(function () use ($path) {
            $oldDir = ftp_pwd($this->connection);
            if (!ftp_chdir($this->connection, $path)) {
                return false;
            }
            if (is_string($oldDir) && $oldDir !== $path) {
                ftp_chdir($this->connection, $oldDir);
            }

            return true;
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::getFileContents()
     */
    public function getFileContents($path)
    {
        return $this->ignoringWarnings(function () use ($path) {
            $fd = fopen('php://memory', 'r+');
            if (!$fd) {
                throw FilesystemException::create(FilesystemException::ERROR_GENERAL, t('Failed to create a temporary memory stream'));
            }
            try {
                $this->connect();
                if (!ftp_fget($this->connection, $fd, $path, FTP_BINARY, 0)) {
                    throw FilesystemException::create(FilesystemException::ERROR_READING_FILE, t('Failed to download file %s via FTP', $path), $path);
                }
                rewind($fd);
                $result = stream_get_contents($fd);
                if ($result === false) {
                    throw FilesystemException::create(FilesystemException::ERROR_GENERAL, t('Failed to read from a temporary memory stream'));
                }

                return $result;
            } finally {
                fclose($fd);
            }
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::setFileContents()
     */
    public function setFileContents($path, $contents)
    {
        $this->ignoringWarnings(function () use ($path, &$contents) {
            $fd = fopen('php://memory', 'r+');
            if (!$fd) {
                throw FilesystemException::create(FilesystemException::ERROR_GENERAL, t('Failed to create a temporary memory stream'));
            }
            try {
                $result = fwrite($fd, $contents);
                if ($result === false) {
                    throw FilesystemException::create(FilesystemException::ERROR_GENERAL, t('Failed to write to a temporary memory stream'));
                }
                rewind($fd);
                $this->connect();
                if (!ftp_fput($this->connection, $path, $fd, FTP_BINARY, 0)) {
                    throw FilesystemException::create(FilesystemException::ERROR_WRITING_FILE, t('Failed to upload file %s via FTP', $path), $path);
                }
            } finally {
                fclose($fd);
            }
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::chmod()
     */
    public function chmod($path, $mode)
    {
        $this->connect();

        $this->ignoringWarnings(function () use ($path, $mode) {
            if (!ftp_chmod($this->connection, $mode, $path) === false) {
                throw FilesystemException::create(FilesystemException::ERROR_SETTING_PERMISSIONS, t('Failed to set the permissions of %s via FTP', $path), $path);
            }
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::createDirectory()
     */
    public function createDirectory($path, $mode = 0777)
    {
        $this->connect();
        $this->ignoringWarnings(function () use ($path, $mode) {
            if (ftp_mkdir($this->connection, $path) === false) {
                throw FilesystemException::create(FilesystemException::ERROR_CREATING_DIRECTORY, t('Failed to create the directory %s via FTP', $path), $path);
            }
        });
        $this->chmod($path, $mode);
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
            $deleted = $this->ignoringWarnings(function () use ($path) {
                return ftp_delete($this->connection, $path);
            });
            if (!$deleted) {
                $failed[] = $path;
            }
        }
        switch (count($failed)) {
            case 0:
                break;
            case 1:
                throw FilesystemException::create(FilesystemException::ERROR_DELETING_FILE, t('Failed to delete the file %s via FTP', $failed[0]), $failed[0]);
            default:
                throw FilesystemException::create(FilesystemException::ERROR_DELETING_FILE, t('Failed to delete the following files via FTP:') . "\n" . implode("\n", $failed), $failed);
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
        $this->ignoringWarnings(function () use ($path) {
            if (!ftp_rmdir($this->connection, $path)) {
                throw FilesystemException::create(FilesystemException::ERROR_DELETING_DIRECTORY, t('Failed to delete the directory %s via FTP', $path), $path);
            }
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::deleteDirectoryIfEmpty()
     */
    public function deleteDirectoryIfEmpty($path)
    {
        $this->connect();

        return $this->ignoringWarnings(function () use ($path) {
            return (bool) ftp_rmdir($this->connection, $path);
        });
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
        if ($this->connection !== null) {
            return;
        }
        $this->ignoringWarnings(function () {
            if ($this->ssl) {
                $connection = ftp_ssl_connect(
                    $this->remoteServer->getHostname(),
                    $this->remoteServer->getPort() ?: $this->defaultPort,
                    $this->remoteServer->getConnectionTimeout() ?: $this->defaultConnectionTimeout
                );
            } else {
                $connection = ftp_connect(
                    $this->remoteServer->getHostname(),
                    $this->remoteServer->getPort() ?: $this->defaultPort,
                    $this->remoteServer->getConnectionTimeout() ?: $this->defaultConnectionTimeout
                );
            }
            if (!$connection) {
                throw FilesystemException::create(FilesystemException::ERROR_CONNECTING, t('Failed to connect to the FTP server'));
            }
            if (!ftp_login(
                $connection,
                $this->remoteServer->getUsername(),
                $this->remoteServer->getPassword()
            )) {
                ftp_close($connection);
                throw FilesystemException::create(FilesystemException::ERROR_CONNECTING, t('Failed to access to the FTP server with the specified username and password'));
            }
            if ($this->passive) {
                if (!ftp_pasv($connection, true)) {
                    ftp_close($connection);
                    throw FilesystemException::create(FilesystemException::ERROR_CONNECTING, t('Failed to enable the FTP passive mode'));
                }
            }
            $this->connection = $connection;
        });
    }

    /**
     * Close the connection (if it's open).
     */
    protected function disconnect()
    {
        $connection = $this->connection;
        $this->connection = null;
        if ($connection !== null) {
            $this->ignoringWarnings(function () use ($connection) {
                ftp_close($connection);
            });
        }
    }
}
