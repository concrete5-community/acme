<?php

namespace Acme\Certificate;

use Acme\Exception\RuntimeException;
use Acme\Security\Crypto;
use Acme\Service\DateTimeParser;
use phpseclib\File\X509;

defined('C5_EXECUTE') or die('Access Denied.');

class CertificateInfoCreator
{
    /**
     * @var \Acme\Service\DateTimeParser
     */
    protected $dateTimeParser;

    /**
     * @var \Acme\Security\Crypto
     */
    protected $crypto;

    /**
     * @param \Acme\Service\DateTimeParser $dateTimeParser
     * @param \Acme\Security\Crypto $crypto
     */
    public function __construct(DateTimeParser $dateTimeParser, Crypto $crypto)
    {
        $this->dateTimeParser = $dateTimeParser;
        $this->crypto = $crypto;
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
            $certificate = $this->crypto->derToPem($certificate, 'CERTIFICATE');
            $issuerCertificate = $this->crypto->derToPem($issuerCertificate, 'CERTIFICATE');
        }
        $x509 = $this->loadCertificate($certificate);
        $startDate = $this->extractStartDate($x509);
        $endDate = $this->extractEndDate($x509);
        $ocspResponderUrl = $this->extractOcspResponderUrl($x509);
        $certifiedDomains = $this->extractNames($x509);
        $issuerCertificates = $this->splitCertificates($issuerCertificate);
        $x509 = $this->loadCertificate(array_shift($issuerCertificates));
        $issuerNames = $this->extractNames($x509);
        if ($issuerNames === []) {
            throw new RuntimeException(t('Failed to detect issuer name'));
        }
        $issuerName = $issuerNames[0];

        return CertificateInfo::create($certificate, $startDate, $endDate, $certifiedDomains, $issuerCertificate, $issuerName, $ocspResponderUrl);
    }

    /**
     * @param string $certificate
     *
     * @return string[]
     */
    protected function splitCertificates($certificate)
    {
        $normalizedCertificate = str_replace("\r", "\n", str_replace("\r\n", "\n", $certificate));
        $normalizedCertificate = preg_replace("/[ \t]+/", ' ', $normalizedCertificate);
        $normalizedCertificate = trim(preg_replace('/\s*\n\s*/', "\n", $normalizedCertificate)) . "\n";
        $matches = null;
        if (!preg_match_all('/(?<certificates>---+ ?BEGIN [^\n]+---+\n.+?\n---+ ?END [^\n]+---+\n)/s', $normalizedCertificate, $matches)) {
            return [$certificate];
        }
        $certificates = array_map('trim', $matches['certificates']);
        if ($normalizedCertificate !== implode("\n", $certificates) . "\n") {
            return [$certificate];
        }

        return $certificates;
    }

    /**
     * @param string $certificate
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \phpseclib\File\X509
     */
    protected function loadCertificate($certificate)
    {
        if (!is_string($certificate) || $certificate === '') {
            throw new RuntimeException(t('The certificate is empty'));
        }
        $x509 = new X509();
        if ($x509->loadX509($certificate) === false) {
            throw new RuntimeException(t('Failed to load the certificate'));
        }

        return $x509;
    }

    /**
     * @param \phpseclib\File\X509 $x509
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \DateTime
     */
    protected function extractStartDate(X509 $x509)
    {
        $value = $this->dateTimeParser->toDateTime(array_get($x509->currentCert, 'tbsCertificate.validity.notBefore.generalTime'));
        if ($value !== null) {
            return $value;
        }
        $value = $this->dateTimeParser->toDateTime(array_get($x509->currentCert, 'tbsCertificate.validity.notBefore.utcTime'));
        if ($value !== null) {
            return $value;
        }

        throw new RuntimeException(t('Failed to determine the initial validity of the certificate'));
    }

    /**
     * @param \phpseclib\File\X509 $x509
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \DateTime
     */
    protected function extractEndDate(X509 $x509)
    {
        $value = $this->dateTimeParser->toDateTime(array_get($x509->currentCert, 'tbsCertificate.validity.notAfter.generalTime'));
        if ($value !== null) {
            return $value;
        }
        $value = $this->dateTimeParser->toDateTime(array_get($x509->currentCert, 'tbsCertificate.validity.notAfter.utcTime'));
        if ($value !== null) {
            return $value;
        }

        throw new RuntimeException(t('Failed to determine the final validity of the certificate'));
    }

    /**
     * @param \phpseclib\File\X509 $x509
     *
     * @return string[]
     */
    protected function extractNames(X509 $x509)
    {
        $result = [];
        $commonName = $x509->getDNProp('id-at-commonName');
        $name = is_array($commonName) ? array_shift($commonName) : $commonName;
        if (is_string($name) && $name !== '') {
            $result[] = $name;
        }

        $altNames = $x509->getExtension('id-ce-subjectAltName');
        if (is_array($altNames)) {
            foreach ($altNames as $altName) {
                if (!is_array($altName)) {
                    continue;
                }
                foreach ($altName as $altNameType => $altNameValue) {
                    switch (is_string($altNameType) ? $altNameType : '') {
                        case 'dNSName':
                            if (is_string($altNameValue) && $altNameValue !== '') {
                                if (!in_array($altNameValue, $result, true)) {
                                    $result[] = $altNameValue;
                                }
                            }
                            break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param \phpseclib\File\X509 $x509
     *
     * @throws \Acme\Exception\Exception
     *
     * @return string
     */
    protected function extractOcspResponderUrl(X509 $x509)
    {
        $methods = $this->getExtensionValue($x509, 'id-pe-authorityInfoAccess');
        if (!is_array($methods)) {
            return '';
        }
        foreach ($methods as $method) {
            if (is_array($method) && array_get($method, 'accessMethod') === 'id-ad-ocsp') {
                $accessLocation = array_get($method, 'accessLocation');
                if (is_array($accessLocation)) {
                    $url = array_get($accessLocation, 'uniformResourceIdentifier');
                    if (is_string($url)) {
                        return $url;
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param \phpseclib\File\X509 $x509
     * @param string $extensionId
     *
     * @return mixed|null
     */
    protected function getExtensionValue(X509 $x509, $extensionId)
    {
        $extensions = array_get($x509->currentCert, 'tbsCertificate.extensions');
        if (!is_array($extensions)) {
            return null;
        }
        foreach ($extensions as $x) {
            if (is_array($x) && array_get($x, 'extnId') === $extensionId) {
                return array_get($x, 'extnValue');
            }
        }

        return null;
    }
}
