<?php

namespace Acme\Crypto;

use Acme\Exception\NotImplementedException;
use Acme\Exception\RuntimeException;
use Acme\Service\Base64EncoderTrait;
use Exception;
use phpseclib\Crypt\RSA as RSA2;
use phpseclib3\Crypt\Common\Formats\Keys\OpenSSH;
use phpseclib3\Crypt\Common\Formats\Keys\PuTTY;
use phpseclib3\Crypt\RSA as RSAKey3;
use phpseclib3\Crypt\RSA\PrivateKey as PrivateKey3;

final class PrivateKey
{
    use Base64EncoderTrait;

    /**
     * PKCS#1 format.
     *
     * @var int
     */
    const FORMAT_PKCS1 = 1;

    /**
     * PKCS#8 format.
     *
     * @var int
     */
    const FORMAT_PKCS8 = 2;

    /**
     * XML format.
     *
     * @var int
     */
    const FORMAT_XML = 3;

    /**
     * PuTTY format.
     *
     * @var int
     */
    const FORMAT_PUTTY = 4;

    /**
     * OpenSSH format.
     *
     * @var int
     */
    const FORMAT_OPENSSH = 5;

    /**
     * @var \phpseclib\Crypt\RSA|\phpseclib3\Crypt\RSA\PrivateKey
     */
    private $value;

    /**
     * @var int
     */
    private $engineID;

    /**
     * @param \phpseclib\Crypt\RSA|\phpseclib3\Crypt\RSA\PrivateKey $value
     * @param int $engineID
     */
    private function __construct($value, $engineID)
    {
        $this->value = $value;
        $this->engineID = $engineID;
    }

    /**
     * @return \phpseclib\Crypt\RSA|\phpseclib3\Crypt\RSA\PrivateKey
     */
    public function getUnderlyingObject()
    {
        return $this->value;
    }

    /**
     * @param string|mixed $value
     * @param int|null $engineID The value of one of the Acme\Crypto\Engine constants
     *
     * @throws \Acme\Exception\RuntimeException
     *
     * @return self
     */
    public static function fromString($value, $engineID = null)
    {
        if (!is_string($value) || $value === '') {
            throw new RuntimeException(t('The specified private key is not valid.'));
        }
        if ($engineID === null) {
            $engineID = Engine::get();
        }
        switch ($engineID) {
            case Engine::PHPSECLIB2:
                $privateKey = new RSA2();
                if ($privateKey->loadKey($value) === false || $privateKey->getPrivateKey() === false || $privateKey->getPublicKey() === false) {
                    throw new RuntimeException(t('The specified private key is not valid.'));
                }

                return new self($privateKey, $engineID);
            case Engine::PHPSECLIB3:
                try {
                    $privateKey = RSAKey3::load($value);
                    if (!$privateKey instanceof PrivateKey3) {
                        throw new RuntimeException(t('The specified private key is not valid.'));
                    }
                } catch (Exception $x) {
                    throw new RuntimeException(t('The specified private key is not valid.'));
                }

                return new self($privateKey, $engineID);
            default:
                throw new NotImplementedException();
        }
    }

    /**
     * @return int
     */
    public function getSize()
    {
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
                return $this->value->getSize();
            case Engine::PHPSECLIB3:
                return $this->value->getLength();
            default:
                throw new NotImplementedException();
        }
    }

    /**
     * @param int $format
     * @param string $comment
     *
     * @return string
     */
    public function getPrivateKeyString($format = self::FORMAT_PKCS1, $comment = '')
    {
        if (!is_string($comment)) {
            $comment = '';
        }
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
                if ($comment !== '') {
                    $obj = clone $this->value;
                    $obj->setComment($comment);
                } else {
                    $obj = $this->value;
                }
                switch ((int) $format) {
                    case self::FORMAT_PKCS1:
                        $result = $obj->getPrivateKey(RSA2::PRIVATE_FORMAT_PKCS1);
                        break;
                    case self::FORMAT_PKCS8:
                        $result = $obj->getPrivateKey(RSA2::PRIVATE_FORMAT_PKCS8);
                        break;
                    case self::FORMAT_XML:
                        $result = $obj->getPrivateKey(RSA2::PRIVATE_FORMAT_XML);
                        break;
                    case self::FORMAT_PUTTY:
                        $result = $obj->getPrivateKey(RSA2::PRIVATE_FORMAT_PUTTY);
                        break;
                    default:
                        $result = false;
                        break;
                }
                if ($result === false) {
                    throw new RuntimeException('Failed to retrieve the private key');
                }

                return $result;
            case Engine::PHPSECLIB3:
                if ($comment !== '') {
                    PuTTY::setComment($comment);
                    OpenSSH::setComment($comment);
                }
                try {
                    switch ((int) $format) {
                        case self::FORMAT_PKCS1:
                            return $this->value->toString('PKCS1');
                        case self::FORMAT_PKCS8:
                            return $this->value->toString('PKCS8');
                        case self::FORMAT_XML:
                            return $this->value->toString('XML');
                        case self::FORMAT_PUTTY:
                            return $this->value->toString('PuTTY');
                        default:
                            throw new RuntimeException('Failed to retrieve the public key');
                    }
                } finally {
                    if ($comment !== '') {
                        PuTTY::setComment('');
                        OpenSSH::setComment('');
                    }
                }
            default:
                throw new NotImplementedException();
        }
    }

    /**
     * @param int $format
     * @param string $comment
     *
     * @return string
     */
    public function getPublicKeyString($format = self::FORMAT_PKCS8, $comment = '')
    {
        if (!is_string($comment)) {
            $comment = '';
        }
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
                if ($comment !== '') {
                    $obj = clone $this->value;
                    $obj->setComment($comment);
                } else {
                    $obj = $this->value;
                }
                switch ((int) $format) {
                    case self::FORMAT_PKCS1:
                        $result = $obj->getPublicKey(RSA2::PUBLIC_FORMAT_PKCS1);
                        break;
                    case self::FORMAT_PKCS8:
                        $result = $obj->getPublicKey(RSA2::PUBLIC_FORMAT_PKCS8);
                        break;
                    case self::FORMAT_XML:
                        $result = $obj->getPublicKey(RSA2::PUBLIC_FORMAT_XML);
                        break;
                    case self::FORMAT_OPENSSH:
                        $result = $obj->getPublicKey(RSA2::PUBLIC_FORMAT_OPENSSH);
                        break;
                    default:
                        $result = false;
                        break;
                }
                if ($result === false) {
                    throw new RuntimeException('Failed to retrieve the public key');
                }

                return $result;
            case Engine::PHPSECLIB3:
                if ($comment !== '') {
                    PuTTY::setComment($comment);
                    OpenSSH::setComment($comment);
                }
                try {
                    switch ((int) $format) {
                        case self::FORMAT_PKCS1:
                            return $this->value->getPublicKey()->toString('PKCS1');
                        case self::FORMAT_PKCS8:
                            return $this->value->getPublicKey()->toString('PKCS8');
                        case self::FORMAT_XML:
                            return $this->value->getPublicKey()->toString('XML');
                        case self::FORMAT_OPENSSH:
                            return $this->value->getPublicKey()->toString('OpenSSH');
                        default:
                            throw new RuntimeException('Failed to retrieve the public key');
                    }
                } finally {
                    if ($comment !== '') {
                        PuTTY::setComment('');
                        OpenSSH::setComment('');
                    }
                }
            default:
                throw new NotImplementedException();
        }
    }

    /**
     * @return self
     */
    public function prepareForSigningRequests()
    {
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
                $clone = clone $this->value;
                if ($clone->sLen === null) {
                    $clone->sLen = false;
                }
                $clone->setHash('sha256');
                $clone->setMGFHash('sha256');
                $clone->setSignatureMode(RSA2::SIGNATURE_PKCS1);

                return new self($clone, $this->engineID);
            case Engine::PHPSECLIB3:
                $clone = $this->value
                    ->withSaltLength(false)
                    ->withHash('sha256')
                    ->withMGFHash('sha256')
                    ->withPadding(PrivateKey3::ENCRYPTION_OAEP | PrivateKey3::SIGNATURE_PKCS1)
                ;

                return new self($clone, $this->engineID);
            default:
                throw new NotImplementedException();
        }
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public function sign($string)
    {
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                $result = $this->value->sign($string);
                if ($result === false) {
                    throw new RuntimeException(t('Failed to sign a message.'));
                }

                return $result;
            default:
                throw new NotImplementedException();
        }
    }

    /**
     * Builds the JWK associated to a private key.
     *
     * @throws \Acme\Exception\KeyPair\MalformedPrivateKeyException when the private key is malformed
     * @throws \Acme\Exception\Codec\Base64EncodingException when we couldn't build a base-64 representation
     *
     * @return array
     */
    public function getJwk()
    {
        return [
            'e' => $this->toBase64UrlSafe($this->getPublicExponentBytes()),
            'kty' => 'RSA',
            'n' => $this->toBase64UrlSafe($this->getModulusBytes()),
        ];
    }

    /**
     * @return string
     */
    private function getPublicExponentBytes()
    {
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
                return $this->value->publicExponent->toBytes();
            case Engine::PHPSECLIB3:
                $components = $this->value->toString('Raw');

                return $components['e']->toBytes();
            default:
                throw new NotImplementedException();
        }
    }

    /**
     * @return string
     */
    private function getModulusBytes()
    {
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
                return $this->value->modulus->toBytes();
            case Engine::PHPSECLIB3:
                $components = $this->value->toString('Raw');

                return $components['n']->toBytes();
            default:
                throw new NotImplementedException();
        }
    }
}
