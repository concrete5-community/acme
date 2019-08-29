<?php

namespace Acme\Entity;

use Acme\Certificate\CertificateInfo;
use DateTime;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents a revoked certificate.
 *
 * @Doctrine\ORM\Mapping\Entity
 * @Doctrine\ORM\Mapping\Table(
 *     name="AcmeRevokedCertificates",
 *     options={"comment":"Revoked certificates"}
 * )
 */
class RevokedCertificate
{
    /**
     * The revoked certificate ID (null if not persisted).
     *
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned":true, "comment":"Revoked certificate ID"})
     * @Doctrine\ORM\Mapping\GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $id;

    /**
     * The record creation date/time.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime", nullable=false, options={"comment":"Record creation date/time"})
     *
     * @var \DateTime
     */
    protected $createdOn;

    /**
     * The associated Certificate instance (if any) that originated this RevokedCertificate instance.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Certificate", inversedBy="revokedCertificates")
     * @Doctrine\ORM\Mapping\JoinColumn(name="parentCertificate", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     *
     * @var \Acme\Entity\Certificate|null
     */
    protected $parentCertificate;

    /**
     * The actual certificate (in PEM format).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Actual certificate (in PEM format)"})
     *
     * @var string
     */
    protected $certificate;

    /**
     * The initial date/time validity of the certificate.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime", nullable=false, options={"comment":"Initial date/time validity of the certificate"})
     *
     * @var \DateTime
     */
    protected $certificateStartDate;

    /**
     * The final date/time validity of the certificate.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime", nullable=false, options={"comment":"Final date/time validity of the certificate"})
     *
     * @var \DateTime
     */
    protected $certificateEndDate;

    /**
     * The list of the actually certified domains (serialized).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"List of the actually certified domains (serialized)"})
     *
     * @var string
     */
    protected $certifiedDomains;

    /**
     * The certificate of the issuer of the certificate.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Certificate of the issuer of the certificate"})
     *
     * @var string
     */
    protected $issuerCertificate;

    /**
     * The name of the certificate issuer.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Name of the certificate issuer"})
     *
     * @var string
     */
    protected $issuerName;

    /**
     * The responder url of the Online Certificate Status Protocol.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Responder url of the Online Certificate Status Protocol"})
     *
     * @var string
     */
    protected $ocspResponderUrl;

    /**
     * The description of the error occurred while revokating the certificate (if any).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Description of the error occurred while revokating the certificate (if any)"})
     *
     * @var string
     */
    protected $revocationFailureMessage;

    /**
     * The info about the certificate.
     *
     * @var \Acme\Certificate\CertificateInfo|null
     */
    private $certificateInfo;

    /**
     * Initialize the instance.
     */
    protected function __construct()
    {
    }

    /**
     * Create a new instance.
     *
     * @param \Acme\Certificate\CertificateInfo $certificateInfo
     *
     * @return static
     */
    public static function create(CertificateInfo $certificateInfo)
    {
        $result = new static();
        $result->createdOn = new DateTime();
        $result->certificateInfo = $certificateInfo;
        $result->certificate = $certificateInfo->getCertificate();
        $result->certificateStartDate = $certificateInfo->getStartDate();
        $result->certificateEndDate = $certificateInfo->getEndDate();
        $result->certifiedDomains = implode("\n", $certificateInfo->getCertifiedDomains());
        $result->issuerCertificate = $certificateInfo->getIssuerCertificate();
        $result->issuerName = $certificateInfo->getIssuerName();
        $result->ocspResponderUrl = $certificateInfo->getOcspResponderUrl();
        $result->setRevocationFailureMessage('');

        return $result;
    }

    /**
     * Get the revoked certificate ID (null if not persisted).
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Get the record creation date/time.
     *
     * @return \DateTime
     */
    public function getCreatedOn()
    {
        return $this->createdOn;
    }

    /**
     * Get the associated Certificate instance (if any) that originated this RevokedCertificate instance.
     *
     * @return \Acme\Entity\Certificate|null
     */
    public function getParentCertificate()
    {
        return $this->parentCertificate;
    }

    /**
     * Set the associated Certificate instance (if any) that originated this RevokedCertificate instance.
     *
     * @param \Acme\Entity\Certificate|null $value
     *
     * @return $this
     */
    public function setParentCertificate(Certificate $value = null)
    {
        $this->parentCertificate = $value;

        return $this;
    }

    /**
     * Get the info about the certificate (if already set).
     *
     * @return \Acme\Certificate\CertificateInfo|null
     */
    public function getCertificateInfo()
    {
        if ($this->certificateInfo === null) {
            $this->certificateInfo = CertificateInfo::create(
                $this->getCertificate(),
                $this->getCertificateStartDate(),
                $this->getCertificateEndDate(),
                $this->getCertifiedDomains(),
                $this->getIssuerCertificate(),
                $this->getIssuerName(),
                $this->getOcspResponderUrl()
            );
        }

        return $this->certificateInfo;
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
    public function getCertificateStartDate()
    {
        return $this->certificateStartDate;
    }

    /**
     * Get the final date/time validity of the certificate.
     *
     * @return \DateTime
     */
    public function getCertificateEndDate()
    {
        return $this->certificateEndDate;
    }

    /**
     * Get the list of the actually certified domains.
     *
     * @return string[]
     */
    public function getCertifiedDomains()
    {
        return $this->certifiedDomains === '' ? [] : explode("\n", $this->certifiedDomains);
    }

    /**
     * Get the certificate of the issuer of the certificate.
     *
     * @return string
     */
    public function getIssuerCertificate()
    {
        return $this->issuerCertificate;
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
     * Get the responder url of the Online Certificate Status Protocol.
     *
     * @return string
     */
    public function getOcspResponderUrl()
    {
        return $this->ocspResponderUrl;
    }

    /**
     * Get the description of the error occurred while revokating the certificate (if any).
     *
     * @return string
     */
    public function getRevocationFailureMessage()
    {
        return $this->revocationFailureMessage;
    }

    /**
     * Set the description of the error occurred while revokating the certificate (if any).
     *
     * @param string $value
     *
     * @return string
     */
    public function setRevocationFailureMessage($value)
    {
        $this->revocationFailureMessage = (string) $value;

        return $this;
    }
}
