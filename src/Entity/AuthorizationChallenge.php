<?php

namespace Acme\Entity;

use DateTime;
use JsonSerializable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Represents a domain authorization and the choosen challenge.
 *
 * @Doctrine\ORM\Mapping\Entity
 * @Doctrine\ORM\Mapping\Table(
 *     name="AcmeAuthorizationChallenges",
 *     indexes={
 *         @Doctrine\ORM\Mapping\Index(name="DomainChallengeStatusToken", columns={"challengeStarted", "challengeToken"})
 *     },
 *     options={"comment":"Domain authorizations and the choosen challenges"}
 * )
 */
class AuthorizationChallenge implements JsonSerializable
{
    /**
     * Authorization status: initial.
     *
     * @var string
     */
    const AUTHORIZATIONSTATUS_PENDING = 'pending';

    /**
     * Authorization status: the challenge status is 'valid'.
     *
     * @var string
     */
    const AUTHORIZATIONSTATUS_VALID = 'valid';

    /**
     * Authorization status: the challenge failed, or errors occurred.
     *
     * @var string
     */
    const AUTHORIZATIONSTATUS_INVALID = 'invalid';

    /**
     * Authorization status: the authorization expired.
     *
     * @var string
     */
    const AUTHORIZATIONSTATUS_EXPIRED = 'expired';

    /**
     * Authorization status: deactivated by the client.
     *
     * @var string
     */
    const AUTHORIZATIONSTATUS_DEACTIVATED = 'deactivated';

    /**
     * Authorization status: revoked by the server.
     *
     * @var string
     */
    const AUTHORIZATIONSTATUS_REVOKED = 'revoked';

    /**
     * Challenge status: initial.
     *
     * @var string
     */
    const CHALLENGESTATUS_PENDING = 'pending';

    /**
     * Challenge status: the client chose the challenge, and the server is attempting to validate the challenge.
     *
     * @var string
     */
    const CHALLENGESTATUS_PROCESSING = 'processing';

    /**
     * Challenge status: the server validated the challenge.
     *
     * @var string
     */
    const CHALLENGESTATUS_VALID = 'valid';

    /**
     * Challenge status: an error occurred.
     *
     * @var string
     */
    const CHALLENGESTATUS_INVALID = 'invalid';

    /**
     * The parent Order entity.
     *
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Order", inversedBy="authorizationChallenges")
     * @Doctrine\ORM\Mapping\JoinColumn(name="parentOrder", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     *
     * @var \Acme\Entity\Order
     */
    protected $parentOrder;

    /**
     * The Domain instance being authorized.
     *
     * @Doctrine\ORM\Mapping\Id
     * @Doctrine\ORM\Mapping\ManyToOne(targetEntity="Domain")
     * @Doctrine\ORM\Mapping\JoinColumn(name="domain", referencedColumnName="id", nullable=false, onDelete="RESTRICT")
     *
     * @var \Acme\Entity\Domain
     */
    protected $domain;

    /**
     * The authorization URL.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Authorization URL"})
     *
     * @var string
     */
    protected $authorizationUrl;

    /**
     * The authorization expiration.
     *
     * @Doctrine\ORM\Mapping\Column(type="datetime", nullable=true, options={"comment":"Authorization expiration"})
     *
     * @var \DateTime|null
     */
    protected $authorizationExpiration;

    /**
     * The authorization status (see the AUTHORIZATIONSTATUS_... constants).
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=30, nullable=false, options={"comment":"Authorization status"})
     *
     * @var string
     */
    protected $authorizationStatus;

    /**
     * The challenge URL.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Challenge URL"})
     *
     * @var string
     */
    protected $challengeUrl;

    /**
     * The challenge token.
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=190, nullable=false, options={"comment":"Challenge token"})
     *
     * @var string
     */
    protected $challengeToken;

    /**
     * The challenge authorization key.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Challenge authorization key"})
     *
     * @var string
     */
    protected $challengeAuthorizationKey;

    /**
     * The challenge status (see the CHALLENGESTATUS_... constants).
     *
     * @Doctrine\ORM\Mapping\Column(type="string", length=30, nullable=false, options={"comment":"Challenge status"})
     *
     * @var string
     */
    protected $challengeStatus;

    /**
     * Did we ask to start the authorization challenge?
     *
     * @Doctrine\ORM\Mapping\Column(type="boolean", nullable=false, options={"comment":"Did we ask to start the authorization challenge?"})
     *
     * @var bool
     */
    protected $challengeStarted;

    /**
     * The challenge failure error message.
     *
     * @Doctrine\ORM\Mapping\Column(type="text", nullable=false, options={"comment":"Challenge failure error message"})
     *
     * @var string
     */
    protected $challengeErrorMessage;

    protected function __construct()
    {
    }

    /**
     * Create a new instance.
     *
     * @return static
     */
    public static function create(Order $parentOrder, Domain $domain)
    {
        $result = new static();
        $result->parentOrder = $parentOrder;
        $result->domain = $domain;

        return $result
            ->setAuthorizationUrl('')
            ->setAuthorizationStatus('')
            ->setChallengeUrl('')
            ->setChallengeToken('')
            ->setChallengeAuthorizationKey('')
            ->setChallengeStatus('')
            ->setIsChallengeStarted(false)
            ->setChallengeErrorMessage('')
        ;
    }

    /**
     * Get the parent Order entity.
     *
     * @return \Acme\Entity\Order
     */
    public function getParentOrder()
    {
        return $this->parentOrder;
    }

    /**
     * Get the Domain instance being authorized.
     *
     * @return \Acme\Entity\Domain
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Get authorization URL.
     *
     * @return string
     */
    public function getAuthorizationUrl()
    {
        return $this->authorizationUrl;
    }

    /**
     * Set authorization URL.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setAuthorizationUrl($value)
    {
        $this->authorizationUrl = $value;

        return $this;
    }

    /**
     * Get the authorization expiration.
     *
     * @return \DateTime|null
     */
    public function getAuthorizationExpiration()
    {
        return $this->authorizationExpiration;
    }

    /**
     * Set the authorization expiration.
     *
     * @return $this
     */
    public function setAuthorizationExpiration(DateTime $value = null)
    {
        $this->authorizationExpiration = $value;

        return $this;
    }

    /**
     * Get the authorization status (see the AUTHORIZATIONSTATUS_... constants).
     *
     * @return string
     */
    public function getAuthorizationStatus()
    {
        return $this->authorizationStatus;
    }

    /**
     * Set the authorization status (see the AUTHORIZATIONSTATUS_... constants).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setAuthorizationStatus($value)
    {
        $this->authorizationStatus = (string) $value;

        return $this;
    }

    /**
     * Get the challenge URL.
     *
     * @return string
     */
    public function getChallengeUrl()
    {
        return $this->challengeUrl;
    }

    /**
     * Set the challenge URL.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setChallengeUrl($value)
    {
        $this->challengeUrl = (string) $value;

        return $this;
    }

    /**
     * Get the challenge token.
     *
     * @return string
     */
    public function getChallengeToken()
    {
        return $this->challengeToken;
    }

    /**
     * Set the challenge token.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setChallengeToken($value)
    {
        $this->challengeToken = (string) $value;

        return $this;
    }

    /**
     * Get the challenge authorization key.
     *
     * @return string
     */
    public function getChallengeAuthorizationKey()
    {
        return $this->challengeAuthorizationKey;
    }

    /**
     * Set the challenge authorization key.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setChallengeAuthorizationKey($value)
    {
        $this->challengeAuthorizationKey = (string) $value;

        return $this;
    }

    /**
     * Get the challenge status (see the CHALLENGESTATUS_... constants).
     *
     * @return string
     */
    public function getChallengeStatus()
    {
        return $this->challengeStatus;
    }

    /**
     * Set the challenge status (see the CHALLENGESTATUS_... constants).
     *
     * @param string $value
     *
     * @return $this
     */
    public function setChallengeStatus($value)
    {
        $this->challengeStatus = (string) $value;

        return $this;
    }

    /**
     * Did we ask to start the authorization challenge?
     *
     * @return string
     */
    public function isChallengeStarted()
    {
        return $this->challengeStarted;
    }

    /**
     * Did we ask to start the authorization challenge?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setIsChallengeStarted($value)
    {
        $this->challengeStarted = (bool) $value;

        return $this;
    }

    /**
     * Get the challenge failure error message.
     *
     * @return string
     */
    public function getChallengeErrorMessage()
    {
        return $this->challengeErrorMessage;
    }

    /**
     * Set the challenge failure error message.
     *
     * @param string $value
     *
     * @return $this
     */
    public function setChallengeErrorMessage($value)
    {
        $this->challengeErrorMessage = (string) $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        return [
            'domain' => $this->getDomain()->getHostDisplayName(),
            'authorizationExpiration' => $this->getAuthorizationExpiration() === null ? null : $this->getAuthorizationExpiration()->getTimestamp(),
            'authorizationStatus' => $this->getAuthorizationStatus(),
            'challengeStatus' => $this->getChallengeStatus(),
            'challengeError' => $this->getChallengeErrorMessage(),
        ];
    }
}
