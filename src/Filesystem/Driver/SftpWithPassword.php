<?php

namespace Acme\Filesystem\Driver;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Driver to work with remoe filesystems via SFTP (with login and password).
 */
final class SftpWithPassword extends Sftp
{
    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::getName()
     */
    public static function getName(array $options)
    {
        return t('SFTP (with login and password)');
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
     * @see \Acme\Filesystem\Driver\Sftp::getLoginParameter()
     */
    protected function getLoginParameter()
    {
        return $this->remoteServer->getPassword();
    }
}
