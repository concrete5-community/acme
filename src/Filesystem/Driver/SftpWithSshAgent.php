<?php

namespace Acme\Filesystem\Driver;

use phpseclib\System\SSH\Agent;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Driver to work with remoe filesystems via SFTP (with login and private key).
 */
class SftpWithSshAgent extends Sftp
{
    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\DriverInterface::getName()
     */
    public static function getName(array $options)
    {
        return t('SFTP (with login, using the SSH agent)');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\RemoteDriverInterface::getLoginFlags()
     */
    public static function getLoginFlags(array $options)
    {
        return static::LOGINFLAG_USERNAME | static::LOGINFLAG_SSHAGENT;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Filesystem\Driver\Sftp::getLoginParameter()
     */
    protected function getLoginParameter()
    {
        $address = $this->remoteServer->getSshAgentSocket();

        return $address === '' ? new Agent() : new Agent($address);
    }
}
