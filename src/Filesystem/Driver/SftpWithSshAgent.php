<?php

namespace Acme\Filesystem\Driver;

use Acme\Crypto\Engine;
use phpseclib\System\SSH\Agent as Agent2;
use phpseclib3\System\SSH\Agent as Agent3;
use RuntimeException;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Driver to work with remoe filesystems via SFTP (with login and private key).
 */
final class SftpWithSshAgent extends Sftp
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
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
                $systemAddress = isset($_SERVER['SSH_AUTH_SOCK']) ? $_SERVER['SSH_AUTH_SOCK'] : null;
                if ($address === '' || $address === $systemAddress) {
                    return new Agent2();
                }
                $_SERVER['SSH_AUTH_SOCK'] = $address;
                try {
                    return new Agent2();
                } finally {
                    if ($systemAddress === null) {
                        unset($_SERVER['SSH_AUTH_SOCK']);
                    } else {
                        $_SERVER['SSH_AUTH_SOCK'] = $systemAddress;
                    }
                }
            case Engine::PHPSECLIB3:
                return new Agent3($address === '' ? null : $address);
            default:
                throw new RuntimeException('Not implemented');
        }
    }
}
