<?php

use Acme\Filesystem\Driver\Ftp;
use Acme\Filesystem\Driver\Local;
use Acme\Filesystem\Driver\SftpWithPassword;
use Acme\Filesystem\Driver\SftpWithPrivateKey;
use Acme\Filesystem\Driver\SftpWithSshAgent;

return [
    'local_driver' => 'local',
    'drivers' => [
        'local' => [
            'class' => Local::class,
            // Turn off the exec() feature of the Local driver.
            // That's because of security reasons and becase users shouldn't use
            // the same operating system user as the one impersonated by the webserver.
            // If you really want to turn this on, use this CLI command:
            // `c5:config set acme::filesystem.drivers.local.enable_exec true`
            'enable_exec' => false,
        ],
        'sftp_password' => [
            'class' => SftpWithPassword::class,
            'default_port' => 22,
            'default_connection_timeout' => 20,
        ],
        'sftp_privatekey' => [
            'class' => SftpWithPrivateKey::class,
            'default_port' => 22,
            'default_connection_timeout' => 20,
        ],
        'sftp_sshagent' => [
            'class' => SftpWithSshAgent::class,
            'default_port' => 22,
            'default_connection_timeout' => 20,
        ],
        'ftp' => [
            'class' => Ftp::class,
            'passive' => false,
            'ssl' => false,
            'default_port' => 21,
            'default_connection_timeout' => 20,
        ],
        'ftp_ssl' => [
            'class' => Ftp::class,
            'passive' => false,
            'ssl' => true,
            'default_port' => 21,
            'default_connection_timeout' => 20,
        ],
        'ftp_pasv' => [
            'class' => Ftp::class,
            'passive' => true,
            'ssl' => false,
            'default_port' => 21,
            'default_connection_timeout' => 20,
        ],
        'ftp_ssl_pasv' => [
            'class' => Ftp::class,
            'passive' => true,
            'ssl' => true,
            'default_port' => 21,
            'default_connection_timeout' => 20,
        ],
    ],
];
