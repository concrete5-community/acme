<?php

namespace Acme\Entity;

use Acme\Server\DirectoryInfoTrait;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents an ACME server.
 *
 * @Doctrine\ORM\Mapping\Entity
 * @Doctrine\ORM\Mapping\Table(
 *     name="AcmeServers",
 *     options={"comment":"ACME servers"}
 * )
 */
class Server
{
    use DirectoryInfoTrait;

    /**
     * The server ID (null if not persisted).
     *
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned":true, "comment":"Server ID"})
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
     * The mnemonic server name.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=190, nullable=false, unique=true, options={"comment":"Mnemonic server name"})
     *
     * @var string
     */
    protected $name;

    /**
     * Is this the default server?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment":"Is this the default server?"})
     *
     * @var bool
     */
    protected $isDefault;

    /**
     * The ACME directory URL.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"ACME directory URL"})
     *
     * @var string
     */
    protected $directoryUrl;

    /**
     * Are unsafe connections allowed?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment":"Are unsafe connections allowed?"})
     *
     * @var bool
     */
    protected $allowUnsafeConnections;

    /**
     * The ports used in HTTP domain authorization challenges (serialized).
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"Ports used in HTTP domain authorization challenges"})
     *
     * @var string
     */
    protected $authorizationPorts;

    /**
     * The ACME protocol version (one of the Version::... constants).
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=50, nullable=false, options={"comment":"ACME protocol version"})
     *
     * @var string
     *
     * @see \Acme\Protocol\Version
     */
    protected $protocolVersion;

    /**
     * The URL to be called to generate new nonces.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"URL to be called to generate new nonces"})
     *
     * @var string
     */
    protected $newNonceUrl;

    /**
     * The URL to be called to register new accounts.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"URL to be called to register new accounts"})
     *
     * @var string
     */
    protected $newAccountUrl;

    /**
     * The URL to be called to authorize domains (always in ACME v1, optional in ACME v2).
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"URL to be called to authorize domains (always in ACME v1, optional in ACME v2)"})
     *
     * @var string
     */
    protected $newAuthorizationUrl;

    /**
     * The URL to be called to request new certificates (only in ACME v1).
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"URL to be called to request new certificates (only in ACME v1)"})
     *
     * @var string
     */
    protected $newCertificateUrl;

    /**
     * The URL to be called to request new certificates (only in ACME v2).
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"URL to be called to request new certificates (only in ACME v2)"})
     *
     * @var string
     */
    protected $newOrderUrl;

    /**
     * The URL to be called to revoce certificates.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"URL to be called to revoce certificates"})
     *
     * @var string
     */
    protected $revokeCertificateUrl;

    /**
     * The URL to be called to read/accept the ACME server terms of service (optional).
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"URL to be called to read/accept the ACME server terms of service (optional)"})
     *
     * @var string
     */
    protected $termsOfServiceUrl;

    /**
     * The URL of the ACME service provider website (optional).
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"URL of the ACME service provider website (optional)"})
     *
     * @var string
     */
    protected $website;

    /**
     * The list of the accounts associated to this server.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="Account", mappedBy="server", cascade={"persist"})
     * @Doctrine\ORM\Mapping\OrderBy({"name"="ASC"})
     *
     * @var \Doctrine\Common\Collections\Collection|\Acme\Entity\Account[]
     */
    protected $accounts;

    protected function __construct()
    {
    }

    /**
     * Create a new instance.
     *
     * @return static
     */
    public static function create()
    {
        $result = new static();
        $result->createdOn = new DateTime();
        $result->accounts = new ArrayCollection();
        $result
            ->setName('')
            ->setIsDefault(false)
            ->setDirectoryUrl('')
            ->setAllowUnsafeConnections(false)
            ->setAuthorizationPorts([])
            ->resetDirectoryInfo()
        ;

        return $result;
    }

    /**
     * Get the server ID (null if not persisted).
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
     * Get the mnemonic server name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the mnemonic server name.
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
     * Is this the default server?
     *
     * @return bool
     */
    public function isDefault()
    {
        return $this->isDefault;
    }

    /**
     * Is this the default server?
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
     * Get the ACME directory URL.
     *
     * @return string
     */
    public function getDirectoryUrl()
    {
        return $this->directoryUrl;
    }

    /**
     * Set the ACME directory URL.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setDirectoryUrl($value)
    {
        $this->directoryUrl = (string) $value;

        return $this;
    }

    /**
     * Are unsafe connections allowed?
     *
     * @return bool
     */
    public function isAllowUnsafeConnections()
    {
        return $this->allowUnsafeConnections;
    }

    /**
     * Are unsafe connections allowed?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setAllowUnsafeConnections($value)
    {
        $this->allowUnsafeConnections = (bool) $value;

        return $this;
    }

    /**
     * Get the ports used in HTTP domain authorization challenges.
     *
     * @return int[]
     */
    public function getAuthorizationPorts()
    {
        return array_values(
            array_filter(
                array_map(
                    'intval',
                    preg_split('/\D+/', (string) $this->authorizationPorts, -1, PREG_SPLIT_NO_EMPTY)
                )
            )
        );
    }

    /**
     * Set the ports used in HTTP domain authorization challenges.
     *
     * @param int[] $value
     *
     * @return $this
     */
    public function setAuthorizationPorts(array $value)
    {
        $this->authorizationPorts = implode(
            ',',
            array_filter(
                array_map(
                    'intval',
                    $value
                )
            )
        );

        return $this;
    }

    /**
     * Get the list of the accounts associated to this server.
     *
     * @return \Doctrine\Common\Collections\Collection|\Acme\Entity\Account[]
     */
    public function getAccounts()
    {
        return $this->accounts;
    }
}
