<?php

namespace Acme\Filesystem\Driver;

use Acme\Exception\FilesystemException;
use Acme\Filesystem\DriverInterface;
use Acme\Filesystem\ExecutableDriverInterface;
use Acme\Filesystem\WritableAwareDriverInterface;
use Acme\Service\NotificationSilencerTrait;
use Concrete\Core\Foundation\Environment\FunctionInspector;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Driver to work with local filesystems.
 */
class Local implements DriverInterface, WritableAwareDriverInterface, ExecutableDriverInterface
{
    use NotificationSilencerTrait;

    /**
     * @var string
     */
    protected $handle;

    /**
     * @var bool
     */
    protected $enableExec;

    /**
     * @param string $handle
     * @param bool $enableExec
     */
    protected function __construct($handle, $enableExec)
    {
        $this->handle = $handle;
        $this->enableExec = $enableExec;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::getName()
     */
    public static function getName(array $options)
    {
        return t('Local filesystem');
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
        return new static($handle, (bool) $options['enable_exec']);
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
     * @see \Acme\Filesystem\DriverInterface::isFile()
     */
    public function isFile($path)
    {
        return $this->ignoringWarnings(function () use ($path) {
            return is_file($path);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::isDirectory()
     */
    public function isDirectory($path)
    {
        return $this->ignoringWarnings(function () use ($path) {
            return is_dir($path);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::getFileContents()
     */
    public function getFileContents($path)
    {
        $contents = $this->ignoringWarnings(function () use ($path) {
            return file_get_contents($path);
        });
        if ($contents === false) {
            throw FilesystemException::create(FilesystemException::ERROR_READING_FILE, t('Failed to read file %s', $path), $path);
        }

        return $contents;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::setFileContents()
     */
    public function setFileContents($path, $contents)
    {
        $written = $this->ignoringWarnings(function () use ($path, &$contents) {
            return file_put_contents($path, $contents);
        });
        if ($written !== strlen($contents)) {
            throw FilesystemException::create(FilesystemException::ERROR_WRITING_FILE, t('Failed to write to the file %s', $path), $path);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::chmod()
     */
    public function chmod($path, $mode)
    {
        $this->ignoringWarnings(function () use ($path, $mode) {
            if (chmod($path, $mode) === false) {
                throw FilesystemException::create(FilesystemException::ERROR_SETTING_PERMISSIONS, t('Failed to set the permissions of %s', $path), $path);
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
        $this->ignoringWarnings(function () use ($path, $mode) {
            for ($i = 0; $i < 3; $i++) {
                if (mkdir($path, $mode)) {
                    return true;
                }
            }
            throw FilesystemException::create(FilesystemException::ERROR_CREATING_DIRECTORY, t('Failed to create the directory %s', $path), $path);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::deleteFile()
     */
    public function deleteFile($paths)
    {
        $this->ignoringWarnings(function () use ($paths) {
            $paths = is_array($paths) ? array_values($paths) : [$paths];
            $failed = [];
            foreach ($paths as $path) {
                for ($i = 0; $i < 3; $i++) {
                    if (unlink($path)) {
                        break;
                    }
                }
                if ($i === 3) {
                    $failed[] = $path;
                }
            }
            switch (count($failed)) {
                case 0:
                    break;
                case 1:
                    throw FilesystemException::create(FilesystemException::ERROR_DELETING_FILE, t('Failed to delete the file %s', $failed[0]), $failed[0]);
                default:
                    throw FilesystemException::create(FilesystemException::ERROR_DELETING_FILE, t('Failed to delete the following files:') . "\n" . implode("\n", $failed), $failed);
            }
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::deleteEmptyDirectory()
     */
    public function deleteEmptyDirectory($path)
    {
        $this->ignoringWarnings(function () use ($path) {
            for ($i = 0; $i < 3; $i++) {
                if (rmdir($path)) {
                    return;
                }
            }
            throw FilesystemException::create(FilesystemException::ERROR_DELETING_DIRECTORY, t('Failed to delete the directory %s', $path), $path);
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::deleteDirectoryIfEmpty()
     */
    public function deleteDirectoryIfEmpty($path)
    {
        try {
            $this->deleteEmptyDirectory($path);

            return true;
        } catch (FilesystemException $x) {
            if ($x->getCode() !== FilesystemException::ERROR_DELETING_DIRECTORY) {
                throw $x;
            }

            return false;
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
        if (!$this->enableExec) {
            throw FilesystemException::create(FilesystemException::ERROR_EXEC_DISABLED, t('The execution of commands in the local server is disabled in the configuration'));
        }

        return $this->ignoringWarnings(function () use ($command, &$output) {
            $rc = -1;
            $lines = [];
            exec($command, $lines, $rc);
            $output = implode("\n", $lines);

            return $rc;
        });
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\WritableAwareDriverInterface::isWritable()
     */
    public function isWritable($path)
    {
        return $this->ignoringWarnings(function () use ($path) {
            return is_writable($path);
        });
    }
}
