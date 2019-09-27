<?php

namespace Acme\Filesystem\Driver;

use Acme\Exception\FilesystemException;
use phpseclib\Crypt\RSA;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Driver to work with remoe filesystems via SFTP (with login and private key).
 */
class SftpWithPrivateKey extends Sftp
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
        $privateKey = $this->remoteServer->getPrivateKey();
        if ($privateKey === '') {
            throw FilesystemException::create(FilesystemException::ERROR_CONNECTING, t('No private key configured for the remote server'));
        }
        $rsa = new RSA();
        if (!$rsa->loadKey($privateKey)) {
            throw FilesystemException::create(FilesystemException::ERROR_CONNECTING, t('The private key of the remote server is malformed'));
        }

        return $rsa;
    }
}
