<?php

namespace Acme\Entity;

use Acme\Certificate\CertificateInfo;
use Acme\Security\KeyPair;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents a certificate issued by an ACME server.
 *
 * @Doctrine\ORM\Mapping\Entity
 * @Doctrine\ORM\Mapping\Table(
 *     name="AcmeCertificates",
 *     options={"comment":"Certificates issued by the ACME server"}
 * )
 */
class Certificate
{
    /**
     * Actions state: none.
     *
     * @var int
     */
    const ACTIONSTATE_NONE = 0;

    /**
     * Actions state flag: problems detected in executed action(s).
     *
     * @var int
     */
    const ACTIONSTATEFLAG_PROBLEMS = 0b1;

    /**
     * Actions state flag: all actions have been executed.
     *
     * @var int
     */
    const ACTIONSTATEFLAG_EXECUTED = 0b10;

    /**
     * The certificate ID (null if not persisted).
     *
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned":true, "comment":"Certificate ID"})
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
     * The account owning this certificate.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Account", inversedBy="certificates")
     * @Doctrine\ORM\Mapping\JoinColumn(name="account", referencedColumnName="id", nullable=false, onDelete="RESTRICT")
     *
     * @var \Acme\Entity\Account
     */
    protected $account;

    /**
     * The certificate private key (in PKCS#1 PEM format).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Certificate private key (in PKCS#1 PEM format)"})
     *
     * @var string
     */
    protected $privateKey;

    /**
     * The public key associated to the certificate private key (in PKCS#1 PEM format).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Public key associated to the certificate private key (in PKCS#1 PEM format)"})
     *
     * @var string
     */
    protected $publicKey;

    /**
     * The currently running order/set of authorizations.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Order", cascade={"all"})
     * @Doctrine\ORM\Mapping\JoinColumn(name="ongoingOrder", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     *
     * @var \Acme\Entity\Order|null
     */
    protected $ongoingOrder;

    /**
     * The certificate signing request (in PEM format).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Certificate signing request (in PEM format)"})
     *
     * @var string
     */
    protected $csr;

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
     * @Doctrine\ORM\Mapping\Column(type="datetime", nullable=true, options={"comment":"Initial date/time validity of the certificate"})
     *
     * @var \DateTime|null
     */
    protected $certificateStartDate;

    /**
     * The final date/time validity of the certificate.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime", nullable=true, options={"comment":"Final date/time validity of the certificate"})
     *
     * @var \DateTime|null
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
     * The actions state flags.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=false, options={"unsigned":true, "comment":"Actions state"})
     *
     * @var int
     */
    protected $actionsState;

    /**
     * The last action that has been executed.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="CertificateAction")
     * @Doctrine\ORM\Mapping\JoinColumn(name="lastActionExecuted", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     *
     * @var \Acme\Entity\CertificateAction|null
     */
    protected $lastActionExecuted;

    /**
     * The list of the domains that this certificate should be valid for.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CertificateDomain", mappedBy="certificate", orphanRemoval=true, cascade={"persist", "remove"})
     * @Doctrine\ORM\Mapping\OrderBy({"isPrimary"="DESC"})
     *
     * @var \Doctrine\Common\Collections\Collection|\Acme\Entity\CertificateDomain[]
     */
    protected $domains;

    /**
     * The list of the actions to be performed after the certificate has been issued.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CertificateAction", mappedBy="certificate", orphanRemoval=true, cascade={"persist", "remove"})
     * @Doctrine\ORM\Mapping\OrderBy({"position"="ASC"})
     *
     * @var \Doctrine\Common\Collections\Collection|\Acme\Entity\CertificateAction[]
     */
    protected $actions;

    /**
     * The list of the orders/sets of authorizations associated to this certificate.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="Order", mappedBy="certificate", cascade={"all"})
     * @Doctrine\ORM\Mapping\OrderBy({"createdOn"="DESC", "id"="DESC"})
     *
     * @var \Doctrine\Common\Collections\Collection|\Acme\Entity\Order[]
     */
    protected $orders;

    /**
     * The list of revoked certificates associated to this instance.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="RevokedCertificate", mappedBy="parentCertificate", cascade={"persist"})
     * @Doctrine\ORM\Mapping\OrderBy({"createdOn"="DESC", "id"="DESC"})
     *
     * @var \Doctrine\Common\Collections\Collection|\Acme\Entity\RevokedCertificate[]
     */
    protected $revokedCertificates;

    /**
     * The private/public key pair.
     *
     * @var \Acme\Security\KeyPair|null
     */
    private $keyPair;

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
     * @param \Acme\Entity\Account $account the account owning this certificate
     *
     * @return static
     */
    public static function create(Account $account)
    {
        $result = new static();
        $result->createdOn = new DateTime();
        $result->account = $account;
        $result->domains = new ArrayCollection();
        $result->actions = new ArrayCollection();
        $result->orders = new ArrayCollection();
        $result->revokedCertificates = new ArrayCollection();
        $result
            ->setKeyPair(null)
            ->setCsr('')
            ->setCertificateInfo(null)
            ->setActionsState(static::ACTIONSTATE_NONE)
        ;

        return $result;
    }

    /**
     * Get the certificate ID (null if not persisted).
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
     * Set the account owning this certificate.
     *
     * @return \Acme\Entity\Account
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * Get the certificate private key (in PKCS#1 PEM format).
     *
     * @return string
     */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    /**
     * Get the public key associated to the certificate private key (in PKCS#1 PEM format).
     *
     * @return string
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * Get the certificate private/public key pair (in PKCS#1 PEM format).
     *
     * @return \Acme\Security\KeyPair|null
     */
    public function getKeyPair()
    {
        if ($this->keyPair === null) {
            $privateKey = $this->getPrivateKey();
            if ($privateKey === '') {
                return null;
            }
            $publicKey = $this->getPublicKey();
            if ($publicKey === '') {
                return null;
            }
            $this->keyPair = KeyPair::create($privateKey, $publicKey);
        }

        return $this->keyPair;
    }

    /**
     * Set the certificate private/public key pair (in PKCS#1 PEM format).
     *
     * @param \Acme\Security\KeyPair|null $value
     *
     * @return $this
     */
    public function setKeyPair(KeyPair $value = null)
    {
        $this->keyPair = $value;
        $this->privateKey = $value === null ? '' : $value->getPrivateKey();
        $this->publicKey = $value === null ? '' : $value->getPublicKey();

        return $this;
    }

    /**
     * Get the currently running order/set of authorizations.
     *
     * @return \Acme\Entity\Order|null
     */
    public function getOngoingOrder()
    {
        return $this->ongoingOrder;
    }

    /**
     * Get the currently running order/set of authorizations.
     *
     * @param \Acme\Entity\Order|null $value
     */
    public function setOngoingOrder(Order $value = null)
    {
        $this->ongoingOrder = $value;

        return $this;
    }

    /**
     * Get the certificate signing request (in PEM format).
     *
     * @return string
     */
    public function getCsr()
    {
        return $this->csr;
    }

    /**
     * Set the certificate signing request (in PEM format).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCsr($value)
    {
        $this->csr = (string) $value;

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
            if ($this->certificate === '' || $this->certificateStartDate === null || $this->certificateEndDate === null) {
                return null;
            }
            $this->certificateInfo = CertificateInfo::create(
                $this->certificate,
                $this->certificateStartDate,
                $this->certificateEndDate,
                $this->certifiedDomains === '' ? [] : explode("\n", $this->certifiedDomains),
                $this->issuerCertificate,
                $this->issuerName,
                $this->ocspResponderUrl
            );
        }

        return $this->certificateInfo;
    }

    /**
     * Set the info about the certificate.
     *
     * @param \Acme\Certificate\CertificateInfo|null $value
     *
     * @return $this
     */
    public function setCertificateInfo(CertificateInfo $value = null)
    {
        $this->certificateInfo = $value;
        $this->certificate = $value === null ? '' : $value->getCertificate();
        $this->certificateStartDate = $value === null ? null : $value->getStartDate();
        $this->certificateEndDate = $value === null ? null : $value->getEndDate();
        $this->certifiedDomains = $value === null ? '' : implode("\n", $value->getCertifiedDomains());
        $this->issuerCertificate = $value === null ? '' : $value->getIssuerCertificate();
        $this->issuerName = $value === null ? '' : $value->getIssuerName();
        $this->ocspResponderUrl = $value === null ? '' : $value->getOcspResponderUrl();

        return $this;
    }

    /**
     * Get the actions state (the value of one of the ACTIONSTATE_... constants / ACTIONSTATEFLAG_... flags).
     *
     * @return int
     */
    public function getActionsState()
    {
        return $this->actionsState;
    }

    /**
     * Set the actions state (the value of one of the ACTIONSTATE_... constants / ACTIONSTATEFLAG_... flags).
     *
     * @param int $value
     *
     * @return $this
     */
    public function setActionsState($value)
    {
        $this->actionsState = (int) $value;

        return $this;
    }

    /**
     * Get the last action that has been executed.
     *
     * @return \Acme\Entity\CertificateAction|null
     */
    public function getLastActionExecuted()
    {
        return $this->lastActionExecuted;
    }

    /**
     * Set the last action that has been executed.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setLastActionExecuted(CertificateAction $value = null)
    {
        $this->lastActionExecuted = $value;

        return $this;
    }

    /**
     * Get the list of the domains that this certificate should be valid for.
     *
     * @return \Doctrine\Common\Collections\Collection|\Acme\Entity\CertificateDomain[]
     */
    public function getDomains()
    {
        return $this->domains;
    }

    /**
     * Get the list of the domain names.
     *
     * @return string[]
     */
    public function getDomainHostDisplayNames()
    {
        $result = [];
        foreach ($this->getDomains() as $certificateDomain) {
            $result[] = $certificateDomain->getDomain()->getHostDisplayName();
        }

        return $result;
    }

    /**
     * Get the list of the actions to be performed after the certificate has been issued.
     *
     * @return \Doctrine\Common\Collections\Collection|\Acme\Entity\CertificateAction[]
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * Get the list of the orders/sets of authorizations associated to this certificate.
     *
     * @return \Doctrine\Common\Collections\Collection|\Acme\Entity\Order[]
     */
    public function getOrders()
    {
        return $this->orders;
    }

    /**
     * Get the list of revoked certificates associated to this instance.
     *
     * @return \Doctrine\Common\Collections\Collection|\Acme\Entity\RevokedCertificate[]
     */
    public function getRevokedCertificates()
    {
        return $this->revokedCertificates;
    }
}
