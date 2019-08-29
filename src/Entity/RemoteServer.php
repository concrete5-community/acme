<?php

namespace Acme\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents a remote server hosting websites.
 *
 * @Doctrine\ORM\Mapping\Entity
 * @Doctrine\ORM\Mapping\Table(
 *     name="AcmeRemoteServers",
 *     options={"comment":"Remote servers hosting websites"}
 *  )
 */
class RemoteServer
{
    /**
     * The remote server ID (null if not persisted).
     *
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned":true, "comment":"Remote server ID"})
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
     * The mnemonic name.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=190, nullable=false, unique=true, options={"comment":"Mnemonic name"})
     *
     * @var string
     */
    protected $name;

    /**
     * The handle of the driver to be used to access the remote server.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=30, nullable=false, options={"comment":"Handle of the driver to be used to access the remote server"})
     *
     * @var string
     */
    protected $driverHandle;

    /**
     * The host name.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"Host name"})
     *
     * @var string
     */
    protected $hostname;

    /**
     * The connection port (if not the default one).
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=true, options={"unsigned":true, "comment":"Connection port (if not the default one)"})
     *
     * @var int|null
     */
    protected $port;

    /**
     * The connection timeout (in seconds).
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=true, options={"unsigned":true, "comment":"Connection timeout (in seconds)"})
     *
     * @var int|null
     */
    protected $connectionTimeout;

    /**
     * The username to be used to connect to the remote server.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"Username to be used to connect to the remote server"})
     *
     * @var string
     */
    protected $username;

    /**
     * The password to be used to connect to the remote server.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"Password to be used to connect to the remote server"})
     *
     * @var string
     */
    protected $password;

    /**
     * The private key to be used to connect to the remote server (in PKCS#1 PEM format).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Private key to be used to connect to the remote server (in PKCS#1 PEM format)"})
     *
     * @var string
     */
    protected $privateKey;

    /**
     * The socket of the SSH Agent to be used to connect to the remote server.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=255, nullable=false, options={"comment":"Socket of the SSH Agent to be used to connect to the remote server"})
     *
     * @var string
     */
    protected $sshAgentSocket;

    /**
     * The list of the certificate actions that use this remote server.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="CertificateAction", mappedBy="remoteServer", cascade={"persist"})
     *
     * @var \Doctrine\Common\Collections\Collection|\Acme\Entity\CertificateAction[]
     */
    protected $certificateActions;

    /**
     * Initialize the instance.
     */
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
        $result->certificateActions = new ArrayCollection();
        $result
            ->setName('')
            ->setDriverHandle('')
            ->setHostname('')
            ->setUsername('')
            ->setPassword('')
            ->setPrivateKey('')
            ->setSshAgentSocket('')
        ;

        return $result;
    }

    /**
     * Get the remote server ID (null if not persisted).
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
     * Get the mnemonic name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the mnemonic name.
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
     * Get the handle of the driver to be used to access the remote server.
     *
     * @return string
     */
    public function getDriverHandle()
    {
        return $this->driverHandle;
    }

    /**
     * Set the handle of the driver to be used to access the remote server.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setDriverHandle($value)
    {
        $this->driverHandle = (string) $value;

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
     * Get the connection port (if not the default one).
     *
     * @return int|null
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Set the connection port (if not the default one).
     *
     * @param int|null $value
     *
     * @return $this
     */
    public function setPort($value)
    {
        $value = (int) $value;
        $this->port = $value > 0 ? $value : null;

        return $this;
    }

    /**
     * Get the connection timeout (in seconds).
     *
     * @return int|null
     */
    public function getConnectionTimeout()
    {
        return $this->connectionTimeout;
    }

    /**
     * Set the connection timeout (in seconds).
     *
     * @param int|null $value
     *
     * @return $this
     */
    public function setConnectionTimeout($value)
    {
        $value = (int) $value;
        $this->connectionTimeout = $value > 0 ? $value : null;

        return $this;
    }

    /**
     * Get the username to be used to connect to the remote server.
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set the username to be used to connect to the remote server.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setUsername($value)
    {
        $this->username = (string) $value;

        return $this;
    }

    /**
     * Get the password to be used to connect to the remote server.
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Set the password to be used to connect to the remote server.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setPassword($value)
    {
        $this->password = (string) $value;

        return $this;
    }

    /**
     * Get the private key to be used to connect to the remote server (in PKCS#1 PEM format).
     *
     * @return string
     */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    /**
     * Get the private key to be used to connect to the remote server (in PKCS#1 PEM format).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setPrivateKey($value)
    {
        $this->privateKey = (string) $value;

        return $this;
    }

    /**
     * Get the socket of the SSH Agent to be used to connect to the remote server.
     *
     * @return string
     */
    public function getSshAgentSocket()
    {
        return $this->sshAgentSocket;
    }

    /**
     * Set the socket of the SSH Agent to be used to connect to the remote server.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setSshAgentSocket($value)
    {
        $this->sshAgentSocket = (string) $value;

        return $this;
    }

    /**
     * Get the list of the certificate actions that use this remote server.
     *
     * @return \Doctrine\Common\Collections\Collection|\Acme\Entity\CertificateAction[]
     */
    public function getCertificateActions()
    {
        return $this->certificateActions;
    }
}
