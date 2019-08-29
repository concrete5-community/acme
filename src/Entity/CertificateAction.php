<?php

namespace Acme\Entity;

use JsonSerializable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents an actions to be performed after a certificate has been issued.
 *
 * @Doctrine\ORM\Mapping\Entity
 * @Doctrine\ORM\Mapping\Table(
 *     name="AcmeCertificateAction",
 *     uniqueConstraints={
 *         @Doctrine\ORM\Mapping\UniqueConstraint(name="AcmeCertificateActionSort", columns={"certificate", "position"})
 *     },
 *     options={"comment":"Actions to be performed after a certificate has been issued"}
 * )
 */
class CertificateAction implements JsonSerializable
{
    /**
     * The certificate action ID (null if not persisted).
     *
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned":true, "comment":"Certificate action ID"})
     * @Doctrine\ORM\Mapping\GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $id;

    /**
     * The certificate this action is for.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Certificate", inversedBy="actions")
     * @Doctrine\ORM\Mapping\JoinColumn(name="certificate", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     *
     * @var \Acme\Entity\Certificate
     */
    protected $certificate;

    /**
     * The ordinal position of this certificate action.
     *
     * @Doctrine\ORM\Mapping\Column(type="integer", nullable=false, options={"unsigned":true, "comment":"Ordinal position of this certificate action"})
     * @Doctrine\ORM\Mapping\GeneratedValue(strategy="AUTO")
     *
     * @var int
     */
    protected $position;

    /**
     * The remote server where the action should occur (NULL for local server).
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="RemoteServer", inversedBy="certificateActions")
     * @Doctrine\ORM\Mapping\JoinColumn(name="remoteServer", referencedColumnName="id", nullable=true, onDelete="RESTRICT")
     *
     * @var \Acme\Entity\RemoteServer|null
     */
    protected $remoteServer;

    /**
     * Save the certificate private key?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment":"Save to file the certificate private key?"})
     *
     * @var bool
     */
    protected $savePrivateKey;

    /**
     * The location where the certificate private key should be saved.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Location where the certificate private key should be saved"})
     *
     * @var string
     */
    protected $savePrivateKeyTo;

    /**
     * Save the certificate?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment":"Save the certificate?"})
     *
     * @var bool
     */
    protected $saveCertificate;

    /**
     * The location where the certificate should be saved.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Location where the certificate should be saved"})
     *
     * @var string
     */
    protected $saveCertificateTo;

    /**
     * Save the issuer certificate?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment":"Save the issuer certificate?"})
     *
     * @var bool
     */
    protected $saveIssuerCertificate;

    /**
     * The location where the issuer certificate should be saved.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Location where the issuer certificate should be saved"})
     *
     * @var string
     */
    protected $saveIssuerCertificateTo;

    /**
     * Save the certificate merged with the issuer certificate?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment":"Save the certificate merged with the issuer certificate?"})
     *
     * @var bool
     */
    protected $saveCertificateWithIssuer;

    /**
     * The location where the certificate merged with the issuer certificate should be saved.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Location where the certificate merged with the issuer certificate should be saved"})
     *
     * @var string
     */
    protected $saveCertificateWithIssuerTo;

    /**
     * Execute a command after saving the certificate files?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment":"Execute a command after saving the certificate files?"})
     *
     * @var bool
     */
    protected $executeCommand;

    /**
     * The command to execute after saving the certificate files.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Command to execute after after saving the certificate files"})
     *
     * @var string
     */
    protected $commandToExecute;

    /**
     * Initializes the instance.
     */
    protected function __construct()
    {
    }

    /**
     * Create a new instance.
     *
     * @param \Acme\Entity\Certificate $certificate
     *
     * @return static
     */
    public static function create(Certificate $certificate)
    {
        $result = new static();
        $result->certificate = $certificate;
        $result
            ->setPosition(0)
            ->setIsSavePrivateKey(false)->setSavePrivateKeyTo('')
            ->setIsSaveCertificate(false)->setSaveCertificateTo('')
            ->setIsSaveIssuerCertificate(false)->setSaveIssuerCertificateTo('')
            ->setIsSaveCertificateWithIssuer(false)->setSaveCertificateWithIssuerTo('')
            ->setIsExecuteCommand(false)->setCommandToExecute('')
        ;

        return $result;
    }

    /**
     * Get the certificate action ID (null if not persisted).
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Get the certificate this action is for.
     *
     * @return \Acme\Entity\Certificate
     */
    public function getCertificate()
    {
        return $this->certificate;
    }

    /**
     * Get the ordinal position of this certificate action.
     *
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set the ordinal position of this certificate action.
     *
     * @param int $value
     *
     * @return $this
     */
    public function setPosition($value)
    {
        $this->position = (int) $value;

        return $this;
    }

    /**
     * Set The remote server where the action should occur (NULL for local server).
     *
     * @param \Acme\Entity\RemoteServer|null $value
     *
     * @return $this
     */
    public function setRemoteServer(RemoteServer $value = null)
    {
        $this->remoteServer = $value;

        return $this;
    }

    /**
     * Get The remote server where the action should occur (NULL for local server).
     *
     * @return \Acme\Entity\RemoteServer|null
     */
    public function getRemoteServer()
    {
        return $this->remoteServer;
    }

    /**
     * Save the certificate private key?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setIsSavePrivateKey($value)
    {
        $this->savePrivateKey = (bool) $value;

        return $this;
    }

    /**
     * Save the certificate private key?
     *
     * @return bool
     */
    public function isSavePrivateKey()
    {
        return $this->savePrivateKey;
    }

    /**
     * Set the location where the certificate private key should be saved.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setSavePrivateKeyTo($value)
    {
        $this->savePrivateKeyTo = (string) $value;

        return $this;
    }

    /**
     * Get location where the certificate private key should be saved.
     *
     * @return string
     */
    public function getSavePrivateKeyTo()
    {
        return $this->savePrivateKeyTo;
    }

    /**
     * Save the certificate?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setIsSaveCertificate($value)
    {
        $this->saveCertificate = (bool) $value;

        return $this;
    }

    /**
     * Save the certificate?
     *
     * @return bool
     */
    public function isSaveCertificate()
    {
        return $this->saveCertificate;
    }

    /**
     * Set the location where the certificate should be saved.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setSaveCertificateTo($value)
    {
        $this->saveCertificateTo = (string) $value;

        return $this;
    }

    /**
     * Get the location where the certificate should be saved.
     *
     * @return string
     */
    public function getSaveCertificateTo()
    {
        return $this->saveCertificateTo;
    }

    /**
     * Save the issuer certificate?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setIsSaveIssuerCertificate($value)
    {
        $this->saveIssuerCertificate = (bool) $value;

        return $this;
    }

    /**
     * Save the issuer certificate?
     *
     * @return bool
     */
    public function isSaveIssuerCertificate()
    {
        return $this->saveIssuerCertificate;
    }

    /**
     * Set the location where the issuer certificate should be saved.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setSaveIssuerCertificateTo($value)
    {
        $this->saveIssuerCertificateTo = (string) $value;

        return $this;
    }

    /**
     * Get the location where the issuer certificate should be saved.
     *
     * @return string
     */
    public function getSaveIssuerCertificateTo()
    {
        return $this->saveIssuerCertificateTo;
    }

    /**
     * Save the certificate merged with the issuer certificate?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setIsSaveCertificateWithIssuer($value)
    {
        $this->saveCertificateWithIssuer = (bool) $value;

        return $this;
    }

    /**
     * Save the certificate merged with the issuer certificate?
     *
     * @return bool
     */
    public function isSaveCertificateWithIssuer()
    {
        return $this->saveCertificateWithIssuer;
    }

    /**
     * Set the location where the certificate merged with the issuer certificate should be saved.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setSaveCertificateWithIssuerTo($value)
    {
        $this->saveCertificateWithIssuerTo = (string) $value;

        return $this;
    }

    /**
     * Get the location where the certificate merged with the issuer certificate should be saved.
     *
     * @return string
     */
    public function getSaveCertificateWithIssuerTo()
    {
        return $this->saveCertificateWithIssuerTo;
    }

    /**
     * Execute a command after saving the certificate files?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setIsExecuteCommand($value)
    {
        $this->executeCommand = (bool) $value;

        return $this;
    }

    /**
     * Execute a command after saving the certificate files?
     *
     * @return bool
     */
    public function isExecuteCommand()
    {
        return $this->executeCommand;
    }

    /**
     * Set the command to execute after saving the certificate files.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCommandToExecute($value)
    {
        $this->commandToExecute = (string) $value;

        return $this;
    }

    /**
     * Get the command to execute after saving the certificate files.
     *
     * @return string
     */
    public function getCommandToExecute()
    {
        return $this->commandToExecute;
    }

    /**
     * {@inheritdoc}
     *
     * @see \JsonSerializable::jsonSerialize
     */
    public function jsonSerialize()
    {
        return [
            'id' => $this->getID(),
            'certificate' => $this->getCertificate()->getID(),
            'position' => $this->getPosition(),
            'remoteServer' => $this->getRemoteServer() ? $this->getRemoteServer()->getID() : '.',
            'savePrivateKey' => $this->isSavePrivateKey(),
            'savePrivateKeyTo' => $this->getSavePrivateKeyTo(),
            'saveCertificate' => $this->isSaveCertificate(),
            'saveCertificateTo' => $this->getSaveCertificateTo(),
            'saveIssuerCertificate' => $this->isSaveIssuerCertificate(),
            'saveIssuerCertificateTo' => $this->getSaveIssuerCertificateTo(),
            'saveCertificateWithIssuer' => $this->isSaveCertificateWithIssuer(),
            'saveCertificateWithIssuerTo' => $this->getSaveCertificateWithIssuerTo(),
            'executeCommand' => $this->isExecuteCommand(),
            'commandToExecute' => $this->getCommandToExecute(),
        ];
    }
}
