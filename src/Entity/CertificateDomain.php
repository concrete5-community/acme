<?php

namespace Acme\Entity;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Joins a Certificate instance with the associated Domain instances.
 *
 * @Doctrine\ORM\Mapping\Entity
 * @Doctrine\ORM\Mapping\Table(
 *     name="AcmeCertificatesDomains",
 *     options={"comment":"Domains associated to every certificate"}
 * )
 */
class CertificateDomain
{
    /**
     * The associated Certificate.
     *
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Certificate", inversedBy="domains")
     * @Doctrine\ORM\Mapping\JoinColumn(name="certificate", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     *
     * @var \Acme\Entity\Certificate
     */
    protected $certificate;

    /**
     * The associated Domain.
     *
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Domain", inversedBy="certificates")
     * @Doctrine\ORM\Mapping\JoinColumn(name="domain", referencedColumnName="id", nullable=false, onDelete="RESTRICT")
     *
     * @var \Acme\Entity\Domain
     */
    protected $domain;

    /**
     * Is this the primary domain for the certificate?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment":"Is this the primary domain for the certificate?"})
     *
     * @var bool
     */
    protected $isPrimary;

    /**
     * Initialize the instance.
     */
    protected function __construct()
    {
    }

    /**
     * Create a new instance.
     *
     * @param \Acme\Entity\Certificate $certificate
     * @param \Acme\Entity\Domain $domain
     *
     * @return static
     */
    public static function create(Certificate $certificate, Domain $domain)
    {
        $result = new static();
        $result->certificate = $certificate;
        $result->domain = $domain;

        return $result
            ->setIsPrimary(false)
        ;
    }

    /**
     * Get the associated Certificate.
     *
     * @return \Acme\Entity\Certificate
     */
    public function getCertificate()
    {
        return $this->certificate;
    }

    /**
     * Get the associated Domain.
     *
     * @return \Acme\Entity\Domain
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Is this the primary domain for the certificate?
     *
     * @return bool
     */
    public function isPrimary()
    {
        return $this->isPrimary;
    }

    /**
     * Is this the primary domain for the certificate?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setIsPrimary($value)
    {
        $this->isPrimary = (bool) $value;

        return $this;
    }
}
