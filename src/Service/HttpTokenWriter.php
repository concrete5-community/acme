<?php

namespace Acme\Service;

use Acme\Exception\FilesystemException;
use Acme\Filesystem\DriverInterface;
use Acme\Http\AuthorizationMiddleware;
use Concrete\Core\Error\UserMessageException;

defined('C5_EXECUTE') or die('Access Denied.');

class HttpTokenWriter
{
    /**
     * @var \Acme\Filesystem\DriverInterface
     */
    protected $driver;

    /**
     * With '/' as directory separator, without trailing '/'.
     *
     * @var string
     */
    protected $webroot;

    /**
     * @param \Acme\Filesystem\DriverInterface $driver
     * @param string $webroot
     */
    public function __construct(DriverInterface $driver, $webroot)
    {
        $this->driver = $driver;
        $this->webroot = rtrim(str_replace('\\', '/', $webroot), '/');
        if ($this->webroot === '') {
            throw new UserMessageException(t('The webroot is not specified'));
        }
    }

    /**
     * Create the authorization file.
     *
     * @param string $token The authorization token
     * @param string $authorizationKey The authorization key
     *
     * @throws \Concrete\Core\Error\UserMessageException
     * @throws \Acme\Exception\FilesystemException
     */
    public function createTokenFile($token, $authorizationKey)
    {
        $this->createChallengeDirectory();
        try {
            $filename = $this->getAbsoluteTokenFilename($token);
            $this->driver->setFileContents($filename, $authorizationKey);
            $this->driver->chmod($filename, 0644);
        } catch (FilesystemException $x) {
            try {
                $this->removeChallengeDirectory();
            } catch (FilesystemException $foo) {
                throw $x;
            }
        }
    }

    /**
     * Delete the authorization file.
     *
     * @param string $token The authorization token
     *
     * @throws \Acme\Exception\FilesystemException
     */
    public function deleteTokenFile($token)
    {
        $filename = $this->getAbsoluteTokenFilename($token);
        if ($this->driver->isFile($filename)) {
            $this->driver->deleteFile($filename);
        }
        $this->removeChallengeDirectory();
    }

    /**
     * Get the path to the token file, relative to the webroot (without leading slash).
     *
     * @param string $token
     *
     * @return string
     */
    public function getRelativeTokenFilename($token)
    {
        return ltrim(AuthorizationMiddleware::ACME_CHALLENGE_PREFIX . $token, '/');
    }

    /**
     * Get the full path to the token file.
     *
     * @param string $token
     *
     * @return string
     */
    public function getAbsoluteTokenFilename($token)
    {
        return $this->webroot . '/' . $this->getRelativeTokenFilename($token);
    }

    /**
     * Get the filesystem driver.
     *
     * @return \Acme\Filesystem\DriverInterface
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * Creates the directory to be used for the HTTP Challenge (if it does not already exist).
     *
     * @throws \Concrete\Core\Error\UserMessageException
     * @throws \Acme\Exception\FilesystemException
     */
    protected function createChallengeDirectory()
    {
        if (!$this->driver->isDirectory($this->webroot)) {
            throw new UserMessageException(t("The directory %s does not exist.", $this->webroot));
        }
        $path = $this->webroot;
        $created = false;
        foreach (explode('/', AuthorizationMiddleware::ACME_CHALLENGE_PREFIX) as $chunk) {
            if ($chunk === '') {
                continue;
            }
            $path .= '/' . $chunk;
            if ($created || !$this->driver->isDirectory($path)) {
                $this->driver->createDirectory($path, 0755);
                $created = true;
            }
        }
    }

    /**
     * Remove the directory to be used for the HTTP Challenge (if it exists and it's empty).
     *
     * @throws \Acme\Exception\FilesystemException
     */
    protected function removeChallengeDirectory()
    {
        if (!$this->driver->isDirectory($this->webroot)) {
            return;
        }
        $paths = [];
        $path = $this->webroot;
        foreach (explode('/', AuthorizationMiddleware::ACME_CHALLENGE_PREFIX) as $chunk) {
            if ($chunk === '') {
                continue;
            }
            $path .= '/' . $chunk;
            $paths[] = $path;
        }
        while (($path = array_pop($paths)) !== null) {
            if ($this->driver->isDirectory($path)) {
                if ($this->driver->deleteDirectoryIfEmpty($path) === false) {
                    return;
                }
            }
        }
    }
}
