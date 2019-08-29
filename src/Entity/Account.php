<?php

namespace Acme\Entity;

use Acme\Security\KeyPair;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents an account of an ACME server.
 *
 * @Doctrine\ORM\Mapping\Entity
 * @Doctrine\ORM\Mapping\Table(
 *     name="AcmeAccounts",
 *     options={"comment":"Accounts on the ACME servers"}
 * )
 */
class Account
{
    /**
     * The account ID (null if not persisted).
     *
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned":true, "comment":"Account ID"})
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
     * The ACME server where this user is registered to.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Server", inversedBy="accounts")
     * @Doctrine\ORM\Mapping\JoinColumn(name="server", referencedColumnName="id", nullable=false, onDelete="RESTRICT")
     *
     * @var \Acme\Entity\Server
     */
    protected $server;

    /**
     * The mnemonic account name.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=190, nullable=false, unique=true, options={"comment":"Mnemonic account name"})
     *
     * @var string
     */
    protected $name;

    /**
     * The registration date/time at ACME server.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime", nullable=true, options={"comment":"Registration date/time at ACME server"})
     *
     * @var \DateTime|null
     */
    protected $registeredOn;

    /**
     * Is this the default account?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment":"Is this the default account?"})
     *
     * @var bool
     */
    protected $isDefault;

    /**
     * The email address associated to this account.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"Email address associated to this account"})
     *
     * @var string
     */
    protected $email;

    /**
     * The account private key (in PKCS#1 PEM format).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Account private key (in PKCS#1 PEM format)"})
     *
     * @var string
     */
    protected $privateKey;

    /**
     * The public key associated to the account private key (in PKCS#1 PEM format).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Public key associated to the account private key (in PKCS#1 PEM format)"})
     *
     * @var string
     */
    protected $publicKey;

    /**
     * The registration URI that identifies this account.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"Registration URI that identifies this account"})
     *
     * @var string
     */
    protected $registrationURI;

    /**
     * The list of the domains associated to this account.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="Domain", mappedBy="account", cascade={"persist"})
     * @Doctrine\ORM\Mapping\OrderBy({"hostname"="ASC"})
     *
     * @var \Doctrine\Common\Collections\Collection|\Acme\Entity\Domain[]
     */
    protected $domains;

    /**
     * The list of the certificates associated to this account.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="Certificate", mappedBy="account", cascade={"persist"})
     *
     * @var \Doctrine\Common\Collections\Collection|\Acme\Entity\Certificate[]
     */
    protected $certificates;

    /**
     * The private/public key pair.
     *
     * @var \Acme\Security\KeyPair|null
     */
    private $keyPair;

    /**
     * Initializes the instance.
     */
    protected function __construct()
    {
    }

    /**
     * Create a new instance.
     *
     * @param \Acme\Entity\Server $server the ACME server where this user is registered to
     *
     * @return static
     */
    public static function create(Server $server)
    {
        $result = new static();
        $result->createdOn = new DateTime();
        $result->server = $server;
        $result->domains = new ArrayCollection();
        $result->certificates = new ArrayCollection();
        $result
            ->setName('')
            ->setIsDefault(false)
            ->setEmail('')
            ->setKeyPair(null)
            ->setRegistrationURI('')
        ;

        return $result;
    }

    /**
     * Get the account ID (null if not persisted).
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
     * Get the ACME server where this user is registered to.
     *
     * @return \Acme\Entity\Server
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * Get the mnemonic account name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the mnemonic account name.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setName($value)
    {
        $this->name = (string) $value;

        return $this;
    }

    /**
     * Get the registration date/time at ACME server.
     *
     * @return \DateTime|null
     */
    public function getRegisteredOn()
    {
        return $this->registeredOn;
    }

    /**
     * Set the registration date/time at ACME server.
     *
     * @param \DateTime|null $value
     *
     * @return $this
     */
    public function setRegisteredOn(DateTime $value = null)
    {
        $this->registeredOn = $value;

        return $this;
    }

    /**
     * Is this the default account?
     *
     * @return bool
     */
    public function isDefault()
    {
        return $this->isDefault;
    }

    /**
     * Is this the default account?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setIsDefault($value)
    {
        $this->isDefault = (bool) $value;

        return $this;
    }

    /**
     * Get the email address associated to this account.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set the email address associated to this account.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setEmail($value)
    {
        $this->email = (string) $value;

        return $this;
    }

    /**
     * Get the account private key (in PKCS#1 PEM format).
     *
     * @return string
     */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    /**
     * Get the public key associated to the account private key (in PKCS#1 PEM format).
     *
     * @return string
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * Get the account private/public key pair (in PKCS#1 PEM format).
     *
     * @return \Acme\Security\KeyPair|null
     */
    public function getKeyPair()
    {
        if ($this->keyPair === null) {
            $privateKey = $this->getPrivateKey();
            $publicKey = $this->getPublicKey();
            if ($privateKey === '' && $publicKey === '') {
                return null;
            }
            $this->keyPair = KeyPair::create($privateKey, $publicKey);
        }

        return $this->keyPair;
    }

    /**
     * Set the account private/public key pair (in PKCS#1 PEM format).
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
     * Get the registration URI that identifies this account.
     *
     * @return string
     */
    public function getRegistrationURI()
    {
        return $this->registrationURI;
    }

    /**
     * Set the registration URI that identifies this account.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setRegistrationURI($value)
    {
        $this->registrationURI = (string) $value;

        return $this;
    }

    /**
     * Get the list of the domains associated to this account.
     *
     * @return \Doctrine\Common\Collections\Collection|\Acme\Entity\Domain[]
     */
    public function getDomains()
    {
        return $this->domains;
    }

    /**
     * Get the list of the certificates associated to this account.
     *
     * @return \Doctrine\Common\Collections\Collection|\Acme\Entity\Certificate[]
     */
    public function getCertificates()
    {
        return $this->certificates;
    }
}
