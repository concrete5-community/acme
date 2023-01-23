<?php

namespace Acme\Crypto;

use Acme\Exception\FileDownloaderException;
use Acme\Exception\RuntimeException;
use Acme\Service\PemDerConversionTrait;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Utility\Service\Validation\Numbers;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class to be used with the 'file_downloader' element.
 */
final class FileDownloader
{
    use PemDerConversionTrait;

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
     * PEM format (base64-encoded PEM with a header and a footer).
     *
     * @var int
     */
    const FORMAT_PEM = 1;

    /**
     * DER format.
     *
     * @var int
     */
    const FORMAT_DER = 2;

    /**
     * @var \Concrete\Core\Utility\Service\Validation\Numbers
     */
    private $numbersHelper;

    /**
     * @var \Concrete\Core\Http\ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var int
     */
    private $engineID;

    /**
     * @param int|null $engineID The value of one of the Acme\Crypto\Engine constants
     */
    public function __construct(Numbers $numbersHelper, ResponseFactoryInterface $responseFactory, $engineID = null)
    {
        $this->numbersHelper = $numbersHelper;
        $this->responseFactory = $responseFactory;
        $this->engineID = $engineID === null ? Engine::get() : $engineID;
    }

    /**
     * Generate a response containing a private key, a public key, a certificate, ...
     *
     * @param int|mixed $what the value of one of the WHAT_... constants
     * @param int|mixed $format the value of one of the Acme\Crypto\PrivateKey::FORMAT_... or Acme\Crypto\FileDownloader::FORMAT_ constants
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
            case self::WHAT_CSR:
                list($mediaType, $filename, $data) = $this->prepareCSR($format, array_get($data, self::WHAT_CSR), $comment);
                break;
            case self::WHAT_CERTIFICATE:
                list($mediaType, $filename, $data) = $this->prepareCertificate($format, array_get($data, self::WHAT_CERTIFICATE), $comment);
                break;
            case self::WHAT_ISSUERCERTIFICATE:
                list($mediaType, $filename, $data) = $this->prepareIssuerCertificate($format, array_get($data, self::WHAT_ISSUERCERTIFICATE), $comment);
                break;
            case self::WHAT_PUBLICKEY:
                list($mediaType, $filename, $data) = $this->preparePublicKey($format, array_get($data, self::WHAT_PRIVATEKEY), $comment);
                break;
            case self::WHAT_PRIVATEKEY:
                list($mediaType, $filename, $data) = $this->preparePrivateKey($format, array_get($data, self::WHAT_PRIVATEKEY), $comment);
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
    private function createResponse($mediaType, $filename, $data)
    {
        return $this->responseFactory->create(
            $data,
            200,
            [
                'Content-Type' => $mediaType,
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
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
    private function prepareCSR($format, $csr, $comment)
    {
        if (!is_string($csr) || $csr === '') {
            throw FileDownloaderException::create(t('The CSR is missing'));
        }
        switch ($format) {
            case self::FORMAT_PEM:
                return ['application/octet-stream', 'csr.pem', $csr];
            case self::FORMAT_DER:
                $der = $this->convertPemToDer($csr);
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
    private function prepareCertificate($format, $certificate, $comment)
    {
        if (!is_string($certificate) || $certificate === '') {
            throw FileDownloaderException::create(t('The certificate is missing'));
        }
        switch ($format) {
            case self::FORMAT_PEM:
                return ['application/octet-stream', 'certificate.pem', $certificate];
            case self::FORMAT_DER:
                $der = $this->convertPemToDer($certificate);
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
    private function prepareIssuerCertificate($format, $issuerCertificate, $comment)
    {
        if (!is_string($issuerCertificate) || $issuerCertificate === '') {
            throw FileDownloaderException::create(t('The issuer certificate is missing'));
        }
        switch ($format) {
            case self::FORMAT_PEM:
                return ['application/octet-stream', 'issuer-certificate.pem', $issuerCertificate];
            case self::FORMAT_DER:
                $der = $this->convertPemToDer($issuerCertificate);
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
     * @param string|mixed $privateKeyString
     * @param string|mixed $comment
     *
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return array
     */
    private function preparePublicKey($format, $privateKeyString, $comment)
    {
        if (!is_string($privateKeyString) || $privateKeyString === '') {
            throw FileDownloaderException::create(t('The private key is missing'));
        }
        try {
            $privateKey = PrivateKey::fromString($privateKeyString, $this->engineID);
        } catch (RuntimeException $x) {
            throw FileDownloaderException::create($x->getMessage());
        }
        $mediaType = 'application/octet-stream';
        switch ($format) {
            case PrivateKey::FORMAT_PKCS1:
                $filename = 'key-public-pkcs1.pub';
                break;
            case PrivateKey::FORMAT_PKCS8:
                $filename = 'key-public-pkcs8.pub';
                break;
            case PrivateKey::FORMAT_XML:
                $mediaType = 'text/xml';
                $filename = 'key-public.xml';
                break;
            case PrivateKey::FORMAT_OPENSSH:
                $filename = 'key-public-openssh.pub';
                break;
            default:
                throw FileDownloaderException::create(t('The format of download is invalid'));
        }
        $data = $privateKey->getPublicKeyString($format, $comment);

        return [$mediaType, $filename, $data];
    }

    /**
     * @param int $format
     * @param string|mixed $privateKeyString
     * @param string|mixed $comment
     *
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return array
     */
    private function preparePrivateKey($format, $privateKeyString, $comment)
    {
        if (!is_string($privateKeyString) || $privateKeyString === '') {
            throw FileDownloaderException::create(t('The private key is missing'));
        }
        try {
            $privateKey = PrivateKey::fromString($privateKeyString, $this->engineID);
        } catch (RuntimeException $x) {
            throw FileDownloaderException::create($x->getMessage());
        }
        $mediaType = 'application/octet-stream';
        switch ($format) {
            case PrivateKey::FORMAT_PKCS1:
                $filename = 'key-private-pkcs1.key';
                break;
            case PrivateKey::FORMAT_PKCS8:
                $filename = 'key-private-pkcs8.key';
                break;
            case PrivateKey::FORMAT_XML:
                $mediaType = 'text/xml';
                $filename = 'key-private.xml';
                break;
            case PrivateKey::FORMAT_PUTTY:
                $filename = 'key-private-putty.ppk';
                break;
            default:
                throw FileDownloaderException::create(t('The format of download is invalid'));
        }
        $data = $privateKey->getPrivateKeyString($format, $comment);

        return [$mediaType, $filename, $data];
    }
}
