<?php

namespace Acme\Filesystem\Driver;

use Acme\Crypto\PrivateKey;
use Acme\Exception\FilesystemException;
use Exception;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Driver to work with remoe filesystems via SFTP (with login and private key).
 */
final class SftpWithPrivateKey extends Sftp
{
    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::getName()
     */
    public static function getName(array $options)
    {
        return t('SFTP (with login and private key)');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\RemoteDriverInterface::getLoginFlags()
     */
    public static function getLoginFlags(array $options)
    {
        return static::LOGINFLAG_USERNAME | static::LOGINFLAG_PRIVATEKEY;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\Driver\Sftp::getLoginParameter()
     */
    protected function getLoginParameter()
    {
        $privateKeyString = $this->remoteServer->getPrivateKey();
        if ($privateKeyString === '') {
            throw FilesystemException::create(FilesystemException::ERROR_CONNECTING, t('No private key configured for the remote server'));
        }
        try {
            $privateKey = PrivateKey::fromString($privateKeyString, $this->engineID);
        } catch (Exception $x) {
            throw FilesystemException::create(FilesystemException::ERROR_CONNECTING, t('The private key of the remote server is malformed'));
        }

        return $privateKey->getUnderlyingObject();
    }
}
