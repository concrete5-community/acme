<?php

namespace Acme\Certificate;

defined('C5_EXECUTE') or die('Access Denied.');

use Acme\Service\CertificateSplitterTrait;
use DateTime;
use JsonSerializable;

/**
 * Contains the info about a certificate.
 */
final class CertificateInfo implements JsonSerializable
{
    use CertificateSplitterTrait;

    /**
     * The actual certificate (in PEM format).
     *
     * @var string
     */
    private $certificate;

    /**
     * The initial date/time validity of the certificate.
     *
     * @var \DateTime
     */
    private $startDate;

    /**
     * The final date/time validity of the certificate.
     *
     * @var \DateTime
     */
    private $endDate;

    /**
     * The list of the actually certified domains.
     *
     * @var string[]
     */
    private $certifiedDomains;

    /**
     * The certificate chain of the issuer of the certificate.
     *
     * @var string
     */
    private $issuerCertificate;

    /**
     * The splitted certificates of the issuer of the certificate.
     *
     * @var string[]|null
     */
    private $issuerCertificates;

    /**
     * The name of the certificate issuer.
     *
     * @var string
     */
    private $issuerName;

    /**
     * The responder url of the Online Certificate Status Protocol.
     *
     * @var string
     */
    private $ocspResponderUrl;

    private function __construct()
    {
    }

    /**
     * Create a new instance.
     *
     * @param string $certificate the actual certificate (in PEM format)
     * @param \DateTime $startDate the initial date/time validity of the certificate
     * @param \DateTime $endDate the final date/time validity of the certificate
     * @param string[] $certifiedDomains The list of the actually certified domains
     * @param string $issuerCertificate the certificate chain of the issuer of the certificate
     * @param string $issuerName the name of the certificate issuer
     * @param string $ocspResponderUrl the responder url of the Online Certificate Status Protocol
     *
     * @return static
     */
    public static function create($certificate, DateTime $startDate, DateTime $endDate, array $certifiedDomains, $issuerCertificate, $issuerName, $ocspResponderUrl)
    {
        $result = new static();
        $result->certificate = (string) $certificate;
        $result->startDate = $startDate;
        $result->endDate = $endDate;
        $result->certifiedDomains = $certifiedDomains;
        $result->issuerCertificate = (string) $issuerCertificate;
        $result->issuerName = (string) $issuerName;
        $result->ocspResponderUrl = (string) $ocspResponderUrl;

        return $result;
    }

    /**
     * Get the actual certificate (in PEM format).
     *
     * @return string
     */
    public function getCertificate()
    {
        return $this->certificate;
    }

    /**
     * Get the initial date/time validity of the certificate.
     *
     * @return \DateTime
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * Get the final date/time validity of the certificate.
     *
     * @return \DateTime
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * Get the list of the actually certified domains.
     *
     * @return string[]
     */
    public function getCertifiedDomains()
    {
        return $this->certifiedDomains;
    }

    /**
     * Get the certificate chain of the issuer of the certificate.
     *
     * @return string
     */
    public function getIssuerCertificate()
    {
        return $this->issuerCertificate;
    }

    /**
     * Get the first certificate of the certificate chain of the issuer of the certificate.
     *
     * @return string
     */
    public function getFirstIssuerCertificate()
    {
        $certificates = $this->getIssuerCertificates();

        return $certificates === [] ? '' : array_shift($certificates);
    }

    /**
     * Get the name of the certificate issuer.
     *
     * @return string
     */
    public function getIssuerName()
    {
        return $this->issuerName;
    }

    /**
     * Get the certificate and the issuer certificate.
     *
     * @return string
     */
    public function getCertificateWithIssuer()
    {
        return trim($this->getCertificate()) . "\n" . trim($this->getIssuerCertificate());
    }

    /**
     * Get the responder url of the Online Certificate Status Protocol.
     *
     * @return string
     */
    public function getOcspResponderUrl()
    {
        return $this->ocspResponderUrl;
    }

    /**
     * {@inheritdoc}
     *
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        return [
            'startDate' => $this->getStartDate()->getTimestamp(),
            'endDate' => $this->getEndDate()->getTimestamp(),
            'certifiedDomains' => $this->getCertifiedDomains(),
            'issuerName' => $this->getIssuerName(),
        ];
    }

    /**
     * Get the splitted certificates of the issuer of the certificate.
     *
     * @return string[]
     */
    private function getIssuerCertificates()
    {
        if ($this->issuerCertificates === null) {
            $this->issuerCertificates = $this->splitCertificates($this->getIssuerCertificate());
        }

        return $this->issuerCertificates;
    }
}
