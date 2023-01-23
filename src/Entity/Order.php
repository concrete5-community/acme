<?php

namespace Acme\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents an order (ACME v02), or a set of authorizations (ACME v01 / ACME v02).
 *
 * @Doctrine\ORM\Mapping\Entity
 * @Doctrine\ORM\Mapping\Table(
 *     name="AcmeOrders",
 *     options={"comment":"Orders for new certificates"}
 * )
 */
class Order implements JsonSerializable
{
    /**
     * Order type: ACME v02 order.
     *
     * @var string
     */
    const TYPE_ORDER = 'order';

    /**
     * Order type: set of authorizations.
     *
     * @var string
     */
    const TYPE_AUTHORIZATION = 'authz';

    /**
     * Order status: initial.
     *
     * @var string
     */
    const STATUS_PENDING = 'pending';

    /**
     * Order status: all the authorizations are in the 'valid' state.
     *
     * @var string
     */
    const STATUS_READY = 'ready';

    /**
     * Order status: the client asked for the certificate issuance by calling the finalize URL (but it's still not available).
     *
     * @var string
     */
    const STATUS_PROCESSING = 'processing';

    /**
     * Order status: the certificate has been issued.
     *
     * @var string
     */
    const STATUS_VALID = 'valid';

    /**
     * Order status: errors occurred (at any stage), or the order expired, or any authorizations completes with something else than 'valid' (that is, 'expired', 'revoked' or 'deactivated').
     *
     * @var string
     */
    const STATUS_INVALID = 'invalid';

    /**
     * The order ID (null if not persisted).
     *
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\Column(type="integer", options={"unsigned":true, "comment":"Order ID"})
     * @Doctrine\ORM\Mapping\GeneratedValue(strategy="AUTO")
     *
     * @var int|null
     */
    protected $id;

    /**
     * The accociated Certificate.
     *
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Certificate", inversedBy="orders")
     * @Doctrine\ORM\Mapping\JoinColumn("certificate", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     *
     * @var \Acme\Entity\Certificate
     */
    protected $certificate;

    /**
     * The record creation date/time.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime", nullable=false, options={"comment":"Record creation date/time"})
     *
     * @var \DateTime
     */
    protected $createdOn;

    /**
     * The record type (one of the Order::TYPE_... constants).
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=20, nullable=false, options={"comment":"Record type"})
     *
     * @var string
     */
    protected $type;

    /**
     * The status of the order/global status of the set of authorizations.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=30, nullable=false, options={"comment":"Status of the order/global status of the set of authorizations"})
     *
     * @var string
     */
    protected $status;

    /**
     * The order expiration order/closest expirations of the authorizations.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime", nullable=true, options={"comment":"Order expiration order/closest expirations of the authorizations"})
     *
     * @var \DateTime|null
     */
    protected $expiration;

    /**
     * The order URL.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Order URL"})
     *
     * @var string
     */
    protected $orderUrl;

    /**
     * The finalize URL (only for orders).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Finalize URL (only for orders)"})
     *
     * @var string
     */
    protected $finalizeUrl;

    /**
     * The certificate URL (only for orders).
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Certificate URL (only for orders)"})
     *
     * @var string
     */
    protected $certificateUrl;

    /**
     * The list of the authorizations+challenges associated to this order/set of authorizations.
     *
     * @Doctrine\ORM\Mapping\OneToMany(targetEntity="AuthorizationChallenge", mappedBy="parentOrder", cascade={"all"})
     *
     * @var \Doctrine\Common\Collections\Collection|\Acme\Entity\AuthorizationChallenge[]
     */
    protected $authorizationChallenges;

    protected function __construct()
    {
    }

    /**
     * Create a new instance.
     *
     * @param string $type
     *
     * @return static
     */
    public static function create(Certificate $certificate, $type)
    {
        $result = new static();
        $result->certificate = $certificate;
        $result->createdOn = new DateTime();
        $result->type = (string) $type;
        $result->authorizationChallenges = new ArrayCollection();

        return $result
            ->setStatus('')
            ->setOrderUrl('')
            ->setFinalizeUrl('')
            ->setCertificateUrl('')
        ;
    }

    /**
     * Get the authorization ID (null if not persisted).
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Get the accociated Certificate.
     *
     * @return \Acme\Entity\Certificate
     */
    public function getCertificate()
    {
        return $this->certificate;
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
     * Get the authorization type (one of the Authorization::TYPE_...).
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get the status of the order/global status of the set of authorizations.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set the status of the order/global status of the set of authorizations.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setStatus($value)
    {
        $this->status = (string) $value;

        return $this;
    }

    /**
     * Get the order expiration order/closest expirations of the authorizations.
     *
     * @return \DateTime|null
     */
    public function getExpiration()
    {
        return $this->expiration;
    }

    /**
     * Set the order expiration order/closest expirations of the authorizations.
     *
     * @return $this
     */
    public function setExpiration(DateTime $value = null)
    {
        $this->expiration = $value;

        return $this;
    }

    /**
     * Get order URL.
     *
     * @return string
     */
    public function getOrderUrl()
    {
        return $this->orderUrl;
    }

    /**
     * Set order URL.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setOrderUrl($value)
    {
        $this->orderUrl = (string) $value;

        return $this;
    }

    /**
     * Get the finalize URL (only for orders).
     *
     * @return string
     */
    public function getFinalizeUrl()
    {
        return $this->finalizeUrl;
    }

    /**
     * Set the finalize URL (only for orders).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setFinalizeUrl($value)
    {
        $this->finalizeUrl = (string) $value;

        return $this;
    }

    /**
     * Get the certificate URL (only for orders).
     *
     * @return string
     */
    public function getCertificateUrl()
    {
        return $this->certificateUrl;
    }

    /**
     * Set the certificate URL (only for orders).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setCertificateUrl($value)
    {
        $this->certificateUrl = (string) $value;

        return $this;
    }

    /**
     * Get the list of the authorizations+challenges associated to this order/set of authorizations.
     *
     * @return \Doctrine\Common\Collections\Collection|\Acme\Entity\AuthorizationChallenge[]
     */
    public function getAuthorizationChallenges()
    {
        return $this->authorizationChallenges;
    }

    /**
     * {@inheritdoc}
     *
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        return [
            'type' => $this->getType(),
            'status' => $this->getStatus(),
            'expiration' => $this->getExpiration() === null ? null : $this->getExpiration()->getTimestamp(),
            'authorizationChallenges' => $this->getAuthorizationChallenges()->toArray(),
        ];
    }
}
