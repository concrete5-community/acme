<?php

namespace Acme\Certificate;

use Acme\Crypto\Engine;
use Acme\Exception\RuntimeException;
use Acme\Service\CertificateSplitterTrait;
use Acme\Service\DateTimeParser;
use Acme\Service\PemDerConversionTrait;

defined('C5_EXECUTE') or die('Access Denied.');

final class CertificateInfoCreator
{
    use CertificateSplitterTrait;

    use PemDerConversionTrait;

    /**
     * @var \Acme\Service\DateTimeParser
     */
    private $dateTimeParser;

    /**
     * @var int
     */
    private $engineID;

    public function __construct(DateTimeParser $dateTimeParser, $engineID = null)
    {
        $this->dateTimeParser = $dateTimeParser;
        $this->engineID = $engineID === null ? Engine::get() : $engineID;
    }

    /**
     * @param string $certificate
     * @param string $issuerCertificate
     * @param bool $convertDerToPem
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Certificate\CertificateInfo
     */
    public function createCertificateInfo($certificate, $issuerCertificate, $convertDerToPem = false)
    {
        if ($convertDerToPem) {
            $certificate = $this->convertDerToPem($certificate, 'CERTIFICATE');
            $issuerCertificate = $this->convertDerToPem($issuerCertificate, 'CERTIFICATE');
        }
        $x509 = X509Inspector::fromString($certificate, $this->engineID);
        $startDate = $x509->extractStartDate($this->dateTimeParser);
        $endDate = $x509->extractEndDate($this->dateTimeParser);
        $ocspResponderUrl = $x509->extractOcspResponderUrl();
        $certifiedDomains = $x509->extractNames();
        $issuerCertificates = $this->splitCertificates($issuerCertificate);
        $x509 = X509Inspector::fromString(array_shift($issuerCertificates), $this->engineID);
        $issuerNames = $x509->extractNames();
        if ($issuerNames === []) {
            throw new RuntimeException(t('Failed to detect issuer name'));
        }
        $issuerName = $issuerNames[0];

        return CertificateInfo::create($certificate, $startDate, $endDate, $certifiedDomains, $issuerCertificate, $issuerName, $ocspResponderUrl);
    }
}
