<?php

namespace Acme\Security;

use Acme\Exception\FileDownloaderException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Utility\Service\Validation\Numbers;
use phpseclib\Crypt\RSA;
use phpseclib\File\X509;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class to be used with the 'file_downloader' element.
 */
class FileDownloader
{
    /**
     * Download type identifier/flag: private keys.
     *
     * @var int
     */
    const WHAT_PRIVATEKEY = 0b1;

    /**
     * Download type identifier/flag: public keys.
     *
     * @var int
     */
    const WHAT_PUBLICKEY = 0b10;

    /**
     * Download type identifier/flag: CSR.
     *
     * @var int
     */
    const WHAT_CSR = 0b100;

    /**
     * Download type identifier/flag: certificate.
     *
     * @var int
     */
    const WHAT_CERTIFICATE = 0b1000;

    /**
     * Download type identifier/flag: issuer certificate.
     *
     * @var int
     */
    const WHAT_ISSUERCERTIFICATE = 0b10000;

    /**
     * @var \Concrete\Core\Utility\Service\Validation\Numbers
     */
    protected $numbersHelper;

    /**
     * @var \Acme\Security\Crypto
     */
    protected $crypto;

    /**
     * @var \Concrete\Core\Http\ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * @param \Concrete\Core\Utility\Service\Validation\Numbers $numbersHelper
     * @param \Acme\Security\Crypto $crypto
     * @param \Concrete\Core\Http\ResponseFactoryInterface $responseFactory
     */
    public function __construct(Numbers $numbersHelper, Crypto $crypto, ResponseFactoryInterface $responseFactory)
    {
        $this->numbersHelper = $numbersHelper;
        $this->crypto = $crypto;
        $this->responseFactory = $responseFactory;
    }

    /**
     * Generate a response containing a private key, a public key, a certificate, ...
     *
     * @param int|mixed $what the value of one of the WHAT_... constants
     * @param int|mixed $format the value of one of the RSA::PUBLIC_FORMAT_... or RSA::PRIVATE_FORMAT_... OR X509::FORMAT_... constants
     * @param array $data Array keys may be WHAT_PRIVATEKEY, WHAT_CSR, WHAT_CERTIFICATE, WHAT_ISSUERCERTIFICATE
     * @param string|mixed $comment an optional comment to be added to the downloaded file (if supported)
     *
     * @throws \Acme\Exception\FileDownloaderException
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function download($what, $format, array $data, $comment = '')
    {
        if (!$this->numbersHelper->integer($what)) {
            throw FileDownloaderException::create(t('The subject of the download is invalid'));
        }
        $what = (int) $what;
        if (!$this->numbersHelper->integer($format)) {
            throw FileDownloaderException::create(t('The format is invalid'));
        }
        $format = (int) $format;
        switch ($what) {
            case static::WHAT_CSR:
                list($mediaType, $filename, $data) = $this->prepareCSR($format, array_get($data, static::WHAT_CSR), $comment);
                break;
            case static::WHAT_CERTIFICATE:
                list($mediaType, $filename, $data) = $this->prepareCertificate($format, array_get($data, static::WHAT_CERTIFICATE), $comment);
                break;
            case static::WHAT_ISSUERCERTIFICATE:
                list($mediaType, $filename, $data) = $this->prepareIssuerCertificate($format, array_get($data, static::WHAT_ISSUERCERTIFICATE), $comment);
                break;
            case static::WHAT_PUBLICKEY:
                list($mediaType, $filename, $data) = $this->preparePublicKey($format, array_get($data, static::WHAT_PRIVATEKEY), $comment);
                break;
            case static::WHAT_PRIVATEKEY:
                list($mediaType, $filename, $data) = $this->preparePrivateKey($format, array_get($data, static::WHAT_PRIVATEKEY), $comment);
                break;
            default:
                throw FileDownloaderException::create(t('The kind of download is invalid'));
        }

        return $this->createResponse($mediaType, $filename, $data);
    }

    /**
     * @param string $mediaType
     * @param string $filename
     * @param string $data
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function createResponse($mediaType, $filename, $data)
    {
        return $this->responseFactory->create(
            $data,
            200,
            [
                'Content-Type' => $mediaType,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * @param int $format
     * @param string|mixed $csr
     * @param string|mixed $comment
     *
     * @return array
     */
    protected function prepareCSR($format, $csr, $comment)
    {
        if (!is_string($csr) || $csr === '') {
            throw FileDownloaderException::create(t('The CSR is missing'));
        }
        switch ($format) {
            case X509::FORMAT_PEM:
                return ['application/octet-stream', 'csr.pem', $csr];
            case X509::FORMAT_DER:
                $der = $this->crypto->pemToDer($csr);
                if ($der === '') {
                    throw FileDownloaderException::create(t('Failed to convert from PEM to DER'));
                }

                return ['application/octet-stream', 'csr.der', $der];
            default:
                throw FileDownloaderException::create(t('The format of download is invalid'));
        }
    }

    /**
     * @param int $format
     * @param string|mixed $certificate
     * @param string|mixed $comment
     *
     * @return array
     */
    protected function prepareCertificate($format, $certificate, $comment)
    {
        if (!is_string($certificate) || $certificate === '') {
            throw FileDownloaderException::create(t('The certificate is missing'));
        }
        switch ($format) {
            case X509::FORMAT_PEM:
                return ['application/octet-stream', 'certificate.pem', $certificate];
            case X509::FORMAT_DER:
                $der = $this->crypto->pemToDer($certificate);
                if ($der === '') {
                    throw FileDownloaderException::create(t('Failed to convert from PEM to DER'));
                }

                return ['application/octet-stream', 'certificate.der', $der];
            default:
                throw FileDownloaderException::create(t('The format of download is invalid'));
        }
    }

    /**
     * @param int $format
     * @param string|mixed $issuerCertificate
     * @param string|mixed $comment
     *
     * @return array
     */
    protected function prepareIssuerCertificate($format, $issuerCertificate, $comment)
    {
        if (!is_string($issuerCertificate) || $issuerCertificate === '') {
            throw FileDownloaderException::create(t('The issuer certificate is missing'));
        }
        switch ($format) {
            case X509::FORMAT_PEM:
                return ['application/octet-stream', 'issuer-certificate.pem', $issuerCertificate];
            case X509::FORMAT_DER:
                $der = $this->crypto->pemToDer($issuerCertificate);
                if ($der === '') {
                    throw FileDownloaderException::create(t('Failed to convert from PEM to DER'));
                }

                return ['application/octet-stream', 'issuer-certificate.der', $der];
            default:
                throw FileDownloaderException::create(t('The format of download is invalid'));
        }
    }

    /**
     * @param int $format
     * @param string|mixed $privateKey
     * @param string|mixed $comment
     *
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return array
     */
    protected function preparePublicKey($format, $privateKey, $comment)
    {
        if (!is_string($privateKey) || $privateKey === '') {
            throw FileDownloaderException::create(t('The private key is missing'));
        }

        $rsa = new RSA();
        if ($rsa->loadKey($privateKey) === false) {
            throw FileDownloaderException::create(t('The private key is malformed'));
        }
        if (is_string($comment) && $comment !== '') {
            $rsa->setComment($comment);
        }
        $mediaType = 'application/octet-stream';
        switch ($format) {
            case RSA::PUBLIC_FORMAT_PKCS1:
                $filename = 'key-public-pkcs1.pub';
                break;
            case RSA::PUBLIC_FORMAT_PKCS8:
                $filename = 'key-public-pkcs8.pub';
                break;
            case RSA::PUBLIC_FORMAT_XML:
                $mediaType = 'text/xml';
                $filename = 'key-public.xml';
                break;
            case RSA::PUBLIC_FORMAT_OPENSSH:
                $filename = 'key-public-openssh.pub';
                break;
            default:
                throw FileDownloaderException::create(t('The format of download is invalid'));
        }
        $data = $rsa->getPublicKey($format);
        if ($data === false) {
            throw FileDownloaderException::create(t('The private key is malformed'));
        }

        return [$mediaType, $filename, $data];
    }

    /**
     * @param int $format
     * @param string|mixed $privateKey
     * @param string|mixed $comment
     *
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return array
     */
    protected function preparePrivateKey($format, $privateKey, $comment)
    {
        if (!is_string($privateKey) || $privateKey === '') {
            throw FileDownloaderException::create(t('The private key is missing'));
        }

        $rsa = new RSA();
        if ($rsa->loadKey($privateKey) === false) {
            throw FileDownloaderException::create(t('The private key is malformed'));
        }
        if (is_string($comment) && $comment !== '') {
            $rsa->setComment($comment);
        }
        $mediaType = 'application/octet-stream';
        switch ($format) {
            case RSA::PRIVATE_FORMAT_PKCS1:
                $filename = 'key-private-pkcs1.key';
                break;
            case RSA::PRIVATE_FORMAT_PKCS8:
                $filename = 'key-private-pkcs8.key';
                break;
            case RSA::PRIVATE_FORMAT_XML:
                $mediaType = 'text/xml';
                $filename = 'key-private.xml';
                break;
            case RSA::PRIVATE_FORMAT_PUTTY:
                $filename = 'key-private-putty.ppk';
                break;
            default:
                throw FileDownloaderException::create(t('The format of download is invalid'));
        }

        $data = $rsa->getPrivateKey($format);
        if ($data === false) {
            throw FileDownloaderException::create(t('The private key is malformed'));
        }

        return [$mediaType, $filename, $data];
    }
}
