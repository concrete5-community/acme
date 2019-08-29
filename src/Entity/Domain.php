<?php

namespace Acme\Entity;

use Acme\ChallengeType\ChallengeTypeInterface;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents a domain that can be certified by an ACME server.
 *
 * @Doctrine\ORM\Mapping\Entity
 * @Doctrine\ORM\Mapping\Table(
 *     name="AcmeDomains",
 *     uniqueConstraints={
 *         @Doctrine\ORM\Mapping\UniqueConstraint(name="AcmeDomainsUnique", columns={"account", "punycode", "isWildcard"})
 *     },
 *     options={"comment":"Authorized domains for the ACME servers"}
 * )
 */
class Domain
{
    /**
     * The domain ID (null if not persisted).
     *
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned":true, "comment":"Domain ID"})
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
     * The account owning this domain.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Account", inversedBy="domains")
     * @Doctrine\ORM\Mapping\JoinColumn(name="account", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     *
     * @var \Acme\Entity\Account
     */
    protected $account;

    /**
     * The host name.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"Host name"})
     *
     * @var string
     */
    protected $hostname;

    /**
     * The punycode of the host name.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=190, nullable=false, options={"comment":"Punycode of the host name"})
     *
     * @var string
     */
    protected $punycode;

    /**
     * Is this a wildcard domain?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment":"Is this a wildcard domain?"})
     *
     * @var bool
     */
    protected $isWildcard;

    /**
     * The handle of the authorization challenge type.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"Handle of the authorization challenge type"})
     *
     * @var string
     */
    protected $challengeTypeHandle;

    /**
     * The data used by the authorization challenge type (in JSON format).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Data used by the authorization challenge type (in JSON format)"})
     *
     * @var string
     */
    protected $challengeTypeConfiguration;

    /**
     * The list of the certificates for this domain.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CertificateDomain", mappedBy="domain", cascade={"persist"})
     *
     * @var \Doctrine\Common\Collections\Collection|\Acme\Entity\CertificateDomain[]
     */
    protected $certificates;

    /**
     * Initialize the instance.
     */
    protected function __construct()
    {
    }

    /**
     * Create a new instance.
     * param \Acme\Entity\Account $account the account owning this domain.
     *
     * @param Account $account
     *
     * @return static
     */
    public static function create(Account $account)
    {
        $result = new static();
        $result->createdOn = new DateTime();
        $result->account = $account;
        $result->certificates = new ArrayCollection();
        $result
            ->setHostname('')
            ->setPunycode('')
            ->setIsWildcard(false)
            ->setChallengeType()
        ;

        return $result;
    }

    /**
     * Get the domain ID (null if not persisted).
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
     * Get the account owning this domain.
     *
     * @return \Acme\Entity\Account
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * Set the host name.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setHostname($value)
    {
        $this->hostname = (string) $value;

        return $this;
    }

    /**
     * Get the host name.
     *
     * @return string
     */
    public function getHostname()
    {
        return $this->hostname;
    }

    /**
     * Get the host display name (that is, prefixing "*." to host name in case of wildcard domain).
     *
     * @return string
     */
    public function getHostDisplayName()
    {
        $result = $this->getHostname();
        if ($result !== '' && $this->isWildcard()) {
            $result = '*.' . $result;
        }

        return $result;
    }

    /**
     * Set the punycode of the host name.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setPunycode($value)
    {
        $this->punycode = (string) $value;

        return $this;
    }

    /**
     * Get the punycode of the host name.
     *
     * @return string
     */
    public function getPunycode()
    {
        return $this->punycode;
    }

    /**
     * Get the punycode display name (that is, prefixing "*." to punycode in case of wildcard domain).
     *
     * @return string
     */
    public function getPunycodeDisplayName()
    {
        $result = $this->getPunycode();
        if ($result !== '' && $this->isWildcard()) {
            $result = '*.' . $result;
        }

        return $result;
    }

    /**
     * Is this a wildcard domain?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setIsWildcard($value)
    {
        $this->isWildcard = (bool) $value;

        return $this;
    }

    /**
     * Is this a wildcard domain?
     *
     * @return bool
     */
    public function isWildcard()
    {
        return $this->isWildcard;
    }

    /**
     * Set the authorization challenge type and its domain-specific configuration.
     *
     * @param \Acme\ChallengeType\ChallengeTypeInterface|null $challengeType
     * @param array $challengeConfiguration
     *
     * @return $this
     */
    public function setChallengeType(ChallengeTypeInterface $challengeType = null, array $challengeConfiguration = [])
    {
        if ($challengeType === null) {
            $this->challengeTypeHandle = '';
            $challengeConfiguration = [];
        } else {
            $this->challengeTypeHandle = $challengeType->getHandle();
        }
        $this->challengeTypeConfiguration = json_encode(
            $challengeConfiguration,
            0
            + JSON_UNESCAPED_SLASHES
            + JSON_UNESCAPED_UNICODE
            + (defined('JSON_UNESCAPED_LINE_TERMINATORS') ? JSON_UNESCAPED_LINE_TERMINATORS : 0)
            + (defined('JSON_THROW_ON_ERROR') ? JSON_THROW_ON_ERROR : 0)
        );

        return $this;
    }

    /**
     * Get the handle of the authorization challenge type.
     *
     * @return string
     */
    public function getChallengeTypeHandle()
    {
        return $this->challengeTypeHandle;
    }

    /**
     * Get the data used by the authorization challenge type.
     *
     * @return array
     */
    public function getChallengeTypeConfiguration()
    {
        if ((string) $this->challengeTypeConfiguration === '') {
            return [];
        }
        $data = json_decode($this->challengeTypeConfiguration, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Get the list of the certificates for this domain.
     *
     * @return \Doctrine\Common\Collections\Collection|\Acme\Entity\CertificateDomain[]
     */
    public function getCertificates()
    {
        return $this->certificates;
    }
}
