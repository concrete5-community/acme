<?php

namespace Acme\Crypto\PrivateKey;

use Acme\Crypto\Engine;
use Acme\Crypto\KeyPair;
use Acme\Crypto\PrivateKey;
use Acme\Exception\FilesystemException;
use Acme\Exception\KeyPair\GenerationTimeoutException;
use Acme\Exception\KeyPair\PrivateKeyTooShortException;
use Acme\Exception\NotImplementedException;
use Acme\Filesystem\DriverManager as FilesystemDriverManager;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\File\Service\File;
use Concrete\Core\Foundation\Environment\FunctionInspector;
use phpseclib\Crypt\RSA as RSA2;
use phpseclib3\Crypt\RSA\PrivateKey as PrivateKey3;

final class Generator
{
    /**
     * @var \Concrete\Core\Config\Repository\Repository
     */
    private $config;

    /**
     * @var int
     */
    private $engineID;

    /**
     * @var \Concrete\Core\File\Service\File
     */
    private $fileService;

    /**
     * @var \Concrete\Core\Foundation\Environment\FunctionInspector
     */
    private $functionInspector;

    /**
     * @var \Acme\Filesystem\DriverManager
     */
    private $filesystemDriverManager;

    /**
     * @param int|null $engineID The value of one of the Acme\Crypto\Engine constants
     */
    public function __construct(Repository $config, File $fileService, FunctionInspector $functionInspector, FilesystemDriverManager $filesystemDriverManager, $engineID = null)
    {
        $this->config = $config;
        $this->fileService = $fileService;
        $this->functionInspector = $functionInspector;
        $this->filesystemDriverManager = $filesystemDriverManager;
        $this->engineID = $engineID === null ? Engine::get() : $engineID;
    }

    /**
     * @param int|null $size
     *
     * @throws \Acme\Exception\KeyPair\PrivateKeyTooShortException when the size of the private key to be created is too small
     * @throws \Acme\Exception\KeyPair\GenerationTimeoutException when the size of the private key to be created is too long and its generation timed out
     * @throws \Acme\Exception\FilesystemException when an error occurred while configuring openssl
     *
     * @return \Acme\Crypto\PrivateKey
     */
    public function generatePrivateKey($size = null)
    {
        if ((string) $size === '') {
            $size = (int) $this->config->get('acme::security.key_size.default');
        } else {
            $size = (int) $size;
            $minimumKeySize = (int) $this->config->get('acme::security.minimumKeySize');
            if ($size < $minimumKeySize) {
                throw PrivateKeyTooShortException::create($size, $minimumKeySize);
            }
        }
        list($openSslConfFile, $deleteOpenSslConfFile) = $this->getOpenSslConfigFilePath();
        try {
            switch ($this->engineID) {
                case Engine::PHPSECLIB2:
                    $rsa = new RSA2();
                    if ($openSslConfFile !== '') {
                        $rsa->configFile = $openSslConfFile;
                    }
                    if ($this->functionInspector->functionAvailable('set_time_limit')) {
                        @set_time_limit(0);
                    }
                    $timeout = false;
                    if ($this->functionInspector->functionAvailable('ini_get')) {
                        $maxTime = ((int) ini_get('max_execution_time')) - 10;
                        if ($maxTime > 0) {
                            $timeout = $maxTime;
                        }
                    }
                    $generated = $rsa->createKey($size, $timeout);
                    if ($generated['partialkey']) {
                        throw GenerationTimeoutException::create($size);
                    }

                    return PrivateKey::fromString($generated['privatekey'], $this->engineID);
                case Engine::PHPSECLIB3:
                    if ($this->functionInspector->functionAvailable('set_time_limit')) {
                        @set_time_limit(0);
                    }
                    if ($openSslConfFile !== '') {
                        PrivateKey3::setOpenSSLConfigPath($openSslConfFile);
                    }
                    try {
                        $generated = PrivateKey3::createKey($size);
                    } finally {
                        PrivateKey3::setOpenSSLConfigPath(null);
                    }

                    return PrivateKey::fromString((string) $generated, $this->engineID);
                default:
                    throw new NotImplementedException();
            }
        } finally {
            if ($deleteOpenSslConfFile) {
                try {
                    $this->filesystemDriverManager->getLocalDriver()->deleteFile($openSslConfFile);
                } catch (FilesystemException $foo) {
                }
            }
        }
    }

    /**
     * @param int|null $size
     *
     * @throws \Acme\Exception\KeyPair\PrivateKeyTooShortException when the size of the private key to be created is too small
     * @throws \Acme\Exception\KeyPair\GenerationTimeoutException when the size of the private key to be created is too long and its generation timed out
     * @throws \Acme\Exception\FilesystemException when an error occurred while configuring openssl
     *
     * @return \Acme\Crypto\KeyPair
     */
    public function generateKeyPair($size = null)
    {
        $privateKey = $this->generatePrivateKey($size);

        return KeyPair::fromPrivateKeyObject($privateKey, $this->engineID);
    }

    /**
     * @throws \Acme\Exception\FilesystemException
     *
     * @return array<string, bool>
     */
    private function getOpenSslConfigFilePath()
    {
        $tempFolder = $this->fileService->getTemporaryDirectory();
        if (!$tempFolder || !is_dir($tempFolder) || !is_writable($tempFolder)) {
            return ['', false];
        }
        $tempFolder = str_replace(DIRECTORY_SEPARATOR, '/', $tempFolder);
        $uniqueInstallationID = $this->config->get('acme::site.unique_installation_id');
        $path = (string) $this->config->get('acme::security.openssl.config_file');
        if ($path === '') {
            $path = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', DIR_FILES_UPLOADED_STANDARD), DIRECTORY_SEPARATOR) . '/.' . md5($uniqueInstallationID) . '-acme.openssl.cnf';
        } else {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        }
        if (is_file($path)) {
            return [$path, false];
        }
        $contents = implode("\n", [
            '# minimalist openssl.cnf file for use with phpseclib, by ACME package',
            '',
            'HOME     = ' . $tempFolder,
            'RANDFILE = ' . $tempFolder . '/.' . md5($uniqueInstallationID) . '-acme.openssl.rnd',
            '',
            '[ v3_ca ]',
            '',
        ]);
        try {
            $this->filesystemDriverManager->getLocalDriver()->setFileContents($path, $contents);
        } catch (FilesystemException $x) {
            return ['', false];
        }

        return [$path, true];
    }
}
