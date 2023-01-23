<?php

namespace Acme\Certificate;

use Acme\Exception\CheckRevocationException;
use Acme\Http\ClientFactory as HttpClientFactory;
use Exception;
use Ocsp\CertificateInfo as OcspCertificateInfo;
use Ocsp\CertificateLoader as OcspCertificateLoader;
use Ocsp\Ocsp;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Class to check certificate revocation.
 */
final class RevocationChecker
{
    /**
     * @var \Ocsp\CertificateLoader
     */
    private $certificateLoader;

    /**
     * @var \Ocsp\CertificateInfo
     */
    private $certificateInfo;

    /**
     * @var \Ocsp\Ocsp
     */
    private $ocsp;

    /**
     * @var \Acme\Http\ClientFactory
     */
    private $httpClientFactory;

    public function __construct(OcspCertificateLoader $certificateLoader, OcspCertificateInfo $certificateInfo, Ocsp $ocsp, HttpClientFactory $httpClientFactory)
    {
        $this->certificateLoader = $certificateLoader;
        $this->certificateInfo = $certificateInfo;
        $this->ocsp = $ocsp;
        $this->httpClientFactory = $httpClientFactory;
    }

    /**
     * Check if a certificate is revoked.
     *
     * @throws \Acme\Exception\CheckRevocationException
     *
     * @return \Ocsp\Response
     */
    public function checkRevocation(CertificateInfo $certificateInfo)
    {
        if ($certificateInfo->getOcspResponderUrl() === '') {
            throw new CheckRevocationException(t('Missing the certificate OCSP Responder URL'));
        }
        try {
            $errorPattern = t('Failed to build the OCSP request: %s');
            $certificate = $this->certificateLoader->fromString($certificateInfo->getCertificate());
            $issuerCertificate = $this->certificateLoader->fromString($certificateInfo->getFirstIssuerCertificate());
            $requestInfo = $this->certificateInfo->extractRequestInfo($certificate, $issuerCertificate);
            $rawRequestBody = $this->ocsp->buildOcspRequestBodySingle($requestInfo);
            $errorPattern = t('Failed send OCSP request: %s');
            $response = $this->httpClientFactory->getClient()->post(
                $certificateInfo->getOcspResponderUrl(),
                $rawRequestBody,
                [
                    'Content-Type' => Ocsp::OCSP_REQUEST_MEDIATYPE,
                ]
            );
            $errorPattern = '%s';
            if ($response->statusCode !== 200) {
                throw new Exception(t('Error querying if the certificate is revoked (HTTP return code: %s)', $response->statusCode));
            }
            $responseContentType = $response->getHeader('Content-Type');
            if ($responseContentType === '' || strcasecmp($responseContentType, Ocsp::OCSP_RESPONSE_MEDIATYPE) !== 0) {
                throw new Exception(t('Error querying if the certificate is revoked (bad %1$s header: expected "%2$s", received "%3$s")', 'Content-Type', Ocsp::OCSP_RESPONSE_MEDIATYPE, $responseContentType));
            }
            $errorPattern = t('Failed to decode the OCSP response: %s');

            return $this->ocsp->decodeOcspResponseSingle($response->body);
        } catch (Exception $x) {
            throw new CheckRevocationException(sprintf($errorPattern, $x->getMessage()));
        } catch (Throwable $x) {
            throw new CheckRevocationException(sprintf($errorPattern, $x->getMessage()));
        }
    }
}
