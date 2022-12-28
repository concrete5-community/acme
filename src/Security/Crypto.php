<?php

namespace Acme\Security;

use Acme\Entity\Account;
use Acme\Exception\Codec\Base64EncodingException;
use Acme\Exception\Codec\JsonEncodingException;
use Acme\Exception\FilesystemException;
use Acme\Exception\KeyPair\GenerationTimeoutException;
use Acme\Exception\KeyPair\MalformedPrivateKeyException;
use Acme\Exception\KeyPair\PrivateKeyTooShortException;
use Acme\Filesystem\DriverManager as FilesystemDriverManager;
use Acme\Service\NotificationSilencerTrait;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\File\Service\File;
use Concrete\Core\Foundation\Environment\FunctionInspector;
use phpseclib\Crypt\Hash;
use phpseclib\Crypt\RSA;

defined('C5_EXECUTE') or die('Access Denied.');

class Crypto
{
    use NotificationSilencerTrait;

    /**
     * @var \Concrete\Core\Config\Repository\Repository
     */
    protected $config;

    /**
     * @var \Concrete\Core\Foundation\Environment\FunctionInspector
     */
    protected $functionInspector;

    /**
     * @var \Concrete\Core\File\Service\File
     */
    protected $fileService;

    /**
     * @var \Acme\Filesystem\DriverManager
     */
    protected $filesystemDriverManager;

    public function __construct(Repository $config, FunctionInspector $functionInspector, File $fileService, FilesystemDriverManager $filesystemDriverManager)
    {
        $this->config = $config;
        $this->functionInspector = $functionInspector;
        $this->fileService = $fileService;
        $this->filesystemDriverManager = $filesystemDriverManager;
    }

    /**
     * Get the size (in bits) of a key.
     *
     * @param \Acme\Security\KeyPair $keyPair
     *
     * @return int|null
     */
    public function getKeySize(KeyPair $keyPair = null)
    {
        if ($keyPair === null) {
            return null;
        }
        $privateKey = $keyPair->getPrivateKey();
        if ($privateKey === '') {
            return null;
        }
        $rsa = new RSA();
        if (!$rsa->loadKey($privateKey)) {
            return null;
        }

        return $rsa->getSize() ?: null;
    }

    /**
     * Get the private/public key pair starting from a private key.
     *
     * @param string|mixed $privateKey
     *
     * @return \Acme\Security\KeyPair|null Return NULL if $privateKey is invalid
     */
    public function getKeyPairFromPrivateKey($privateKey)
    {
        if (!is_string($privateKey) || $privateKey === '') {
            return null;
        }
        $rsa = new RSA();
        if (!$rsa->loadKey($privateKey)) {
            return null;
        }
        $normalizedPrivateKey = $rsa->getPrivateKey();
        if (!$normalizedPrivateKey) {
            return null;
        }
        $publicKey = $rsa->getPublicKey();
        if (!$publicKey) {
            return null;
        }

        return KeyPair::create($normalizedPrivateKey, $publicKey);
    }

    /**
     * Generate a new private/public key pair.
     *
     * @param int|null $size
     *
     * @throws \Acme\Exception\KeyPair\PrivateKeyTooShortException when the size of the private key to be created is too short
     * @throws \Acme\Exception\KeyPair\GenerationTimeoutException when the size of the private key to be created is too long and its generation timed out
     *
     * @return \Acme\Security\KeyPair
     */
    public function generateKeyPair($size = null)
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
            $rsa = new RSA();
            if ($openSslConfFile !== '') {
                $rsa->configFile = $openSslConfFile;
            }
            $timeout = false;
            if ($this->functionInspector->functionAvailable('set_time_limit') && $this->functionInspector->functionAvailable('ini_get')) {
                @set_time_limit(0);
                $maxTime = ((int) ini_get('max_execution_time')) - 10;
                if ($maxTime > 0) {
                    $timeout = $maxTime;
                }
            }
            $generated = $rsa->createKey($size, $timeout);
            if ($generated['partialkey']) {
                throw GenerationTimeoutException::create($size);
            }

            return KeyPair::create($generated['privatekey'], $generated['publickey']);
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
     * Builds the JWK associated to a private key.
     *
     * @param \phpseclib\Crypt\RSA|string $privateKey
     *
     * @throws \Acme\Exception\KeyPair\MalformedPrivateKeyException when the private key is malformed
     * @throws \Acme\Exception\Codec\Base64EncodingException when we couldn't build a base-64 representation
     *
     * @return array
     */
    public function getJwk($privateKey)
    {
        if ($privateKey instanceof RSA) {
            $rsa = $privateKey;
        } else {
            $rsa = new RSA();
            if ($rsa->loadKey($privateKey) === false) {
                throw MalformedPrivateKeyException::create($privateKey);
            }
        }
        if ($rsa->getPrivateKey() === false) {
            throw MalformedPrivateKeyException::create();
        }

        return [
            'e' => $this->toBase64($rsa->publicExponent->toBytes()),
            'kty' => 'RSA',
            'n' => $this->toBase64($rsa->modulus->toBytes()),
        ];
    }

    /**
     * Render some data in JSON format, and encodes it in base64.
     *
     * @param mixed $data
     *
     * @throws \Acme\Exception\Codec\JsonEncodingException when we couldn't get the JSON represetation
     * @throws \Acme\Exception\Codec\Base64EncodingException when we couldn't convert to base-64
     *
     * @return string
     */
    public function toJsonBase64($data)
    {
        return $this->toBase64($this->toJson($data));
    }

    /**
     * Render a variable in JSON format.
     *
     * @param mixed $data
     *
     * @throws \Acme\Exception\Codec\JsonEncodingException when we couldn't convert $data to json
     *
     * @return string
     */
    public function toJson($data)
    {
        if ($data === []) {
            return '{}';
        }
        $json = $this->ignoringWarnings(function () use ($data) {
            return json_encode($data, JSON_UNESCAPED_SLASHES);
        });

        if ($json === false) {
            throw JsonEncodingException::create($data);
        }

        return $json;
    }

    /**
     * Render a variable to base 64 encoding with URL and filename safe alphabet.
     *
     * @param string $str
     *
     * @throws \Acme\Exception\Codec\Base64EncodingException when we couldn't convert $data to base-64
     *
     * @return string
     *
     * @see https://tools.ietf.org/html/rfc4648#section-5
     */
    public function toBase64($str)
    {
        $base64 = $this->ignoringWarnings(function () use ($str) {
            return base64_encode($str);
        });
        if ($base64 === false) {
            throw Base64EncodingException::create($str);
        }

        return rtrim(strtr($base64, '+/', '-_'), '=');
    }

    /**
     * Convert an ASCII representation of a key (PEM) to its binary representation (DER).
     *
     * @param string $value
     *
     * @return string empty string in case of problems
     */
    public function pemToDer($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^-.+-$/ms', $value)) {
            return '';
        }
        $value = preg_replace('/.*?^-+[^-]+-+/ms', '', $value, 1);
        $value = preg_replace('/-+[^-]+-+/', '', $value);
        $value = str_replace(["\r", "\n", ' '], '', $value);
        if (!preg_match('#^[a-zA-Z\d/+]*={0,2}$#', $value)) {
            return '';
        }
        $value = $this->ignoringWarnings(function () use ($value) {
            return base64_decode($value);
        });

        return $value ?: '';
    }

    /**
     * Convert a binary representation of a key (DER) to its ASCII representation (PEM).
     *
     * @param string $value the binary data
     * @param string $kind the kind of the data (eg 'CERTIFICATE')
     *
     * @return string|null
     */
    public function derToPem($value, $kind)
    {
        return "-----BEGIN {$kind}-----\n" . chunk_split(base64_encode($value), 64) . "-----END {$kind}-----";
    }

    /**
     * Generate the authorization challenge authorization key.
     *
     * @param \Acme\Entity\Account $account
     * @param string $challengeToken
     *
     * @throws \Acme\Exception\Codec\Base64EncodingException when we couldn't build a base-64 representation
     * @throws \Acme\Exception\KeyPair\MalformedPrivateKeyException when the private key is malformed
     * @throws \Acme\Exception\Codec\JsonEncodingException when we couldn't convert $data to json
     *
     * @return string
     */
    public function generateChallengeAuthorizationKey(Account $account, $challengeToken)
    {
        return $challengeToken . '.' . $this->getPrivateKeyThumbprint($account->getPrivateKey());
    }

    /**
     * Generate the value to be saved in DNS records for dns-01 challenge types.
     *
     * @param string $authorizationKey
     *
     * @return string
     */
    public function generateDnsRecordValue($authorizationKey)
    {
        $hasher = new Hash('sha256');
        $digest = $hasher->hash($authorizationKey);

        return $this->crypto->toBase64($digest);
    }

    /**
     * Get the thumbprint of a private key.
     *
     * @param string $privateKey
     *
     * @throws \Acme\Exception\Codec\Base64EncodingException when we couldn't build a base-64 representation
     * @throws \Acme\Exception\KeyPair\MalformedPrivateKeyException when the private key is malformed
     * @throws \Acme\Exception\Codec\JsonEncodingException when we couldn't convert $data to json
     *
     * @return string
     */
    protected function getPrivateKeyThumbprint($privateKey)
    {
        $jwkJson = $this->toJson($this->getJwk($privateKey));
        $hasher = new Hash('sha256');
        $hash = $hasher->hash($jwkJson);

        return $this->toBase64($hash);
    }

    /**
     * @return array<string, bool>
     */
    protected function getOpenSslConfigFilePath()
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
