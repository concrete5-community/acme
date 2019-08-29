<?php

namespace Acme\Order;

use Acme\ChallengeType\ChallengeTypeManager;
use Acme\Entity\AuthorizationChallenge;
use Acme\Entity\Certificate;
use Acme\Entity\Domain;
use Acme\Entity\Order;
use Acme\Exception\RuntimeException;
use Acme\Protocol\Response;
use Acme\Security\Crypto;
use Acme\Service\DateTimeParser;

defined('C5_EXECUTE') or die('Access Denied.');

class OrderUnserializer
{
    /**
     * @var \Acme\Service\DateTimeParser
     */
    protected $dateTimeParser;

    /**
     * @var \Acme\ChallengeType\ChallengeTypeManager
     */
    protected $challengeTypeManager;

    /**
     * @var \Acme\Security\Crypto
     */
    protected $crypto;

    /**
     * @param \Acme\Service\DateTimeParser $dateTimeParser
     * @param \Acme\ChallengeType\ChallengeTypeManager $challengeTypeManager
     * @param \Acme\Security\Crypto $crypto
     */
    public function __construct(DateTimeParser $dateTimeParser, ChallengeTypeManager $challengeTypeManager, Crypto $crypto)
    {
        $this->dateTimeParser = $dateTimeParser;
        $this->challengeTypeManager = $challengeTypeManager;
        $this->crypto = $crypto;
    }

    /**
     * @param \Acme\Entity\Certificate $certificate
     * @param \Acme\Protocol\Response[] $authorizationResponses
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Entity\Order
     */
    public function unserializeAuthorizationRequests(Certificate $certificate, array $authorizationResponses)
    {
        $result = Order::create($certificate, Order::TYPE_AUTHORIZATION);
        $this->unserializeAuthorizationChallenges($result, $authorizationResponses, null);
        $this->updateMainAuthorizationSetRecord($result);

        return $result;
    }

    /**
     * @param \Acme\Entity\Certificate $certificate
     * @param \Acme\Protocol\Response $mainResponse
     * @param \Acme\Protocol\Response[] $childResponses
     * @param string $type One of the Order::TYPE_... constants
     * @param Response $orderResponse
     * @param array $authorizationResponses
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Entity\Order
     */
    public function unserializeOrder(Certificate $certificate, Response $orderResponse, array $authorizationResponses)
    {
        $orderData = $orderResponse->getData();
        $result = Order::create($certificate, Order::TYPE_ORDER)
            ->setOrderUrl($orderResponse->getLocation())
            ->setFinalizeUrl($this->arrayGetNonEmptyString($orderData, 'finalize'))
        ;
        $this->unserializeAuthorizationChallenges($result, $authorizationResponses, array_get($orderData, 'authorizations', []));
        $this->updateMainOrderRecord($result, $orderData);

        return $result;
    }

    /**
     * @param \Acme\Entity\AuthorizationChallenge $authorizationChallenge
     * @param array $authorization
     * @param array $challenge
     */
    public function updateAuthorizationChallenge(AuthorizationChallenge $authorizationChallenge, array $authorization, array $challenge)
    {
        $authorizationChallenge
            ->setAuthorizationExpiration($this->dateTimeParser->toDateTime(array_get($authorization, 'expires')))
            ->setAuthorizationStatus($this->arrayGetNonEmptyString($authorization, 'status'))
            ->setChallengeStatus($this->arrayGetNonEmptyString($challenge, 'status'))
            ->setChallengeErrorMessage(array_get($challenge, 'error.detail'))
        ;
    }

    /**
     * @param \Acme\Entity\Order $order
     * @param array $data
     */
    public function updateMainOrderRecord(Order $order, array $data)
    {
        $order
            ->setStatus($this->arrayGetNonEmptyString($data, 'status'))
            ->setExpiration($this->dateTimeParser->toDateTime(array_get($data, 'expires')))
            ->setCertificateUrl(array_get($data, 'certificate', ''))
        ;
    }

    /**
     * @param \Acme\Entity\Order $order
     *
     * @throws \Acme\Exception\Exception
     */
    public function updateMainAuthorizationSetRecord(Order $order)
    {
        if ($order->getType() !== Order::TYPE_AUTHORIZATION) {
            throw new RuntimeException(t('Expecting a set of authorizations, got %s', $order->getType()));
        }
        $authorizationStatuses = [];
        $closestAuthorizationExpiration = null;
        foreach ($order->getAuthorizationChallenges() as $authorizationChallenge) {
            if ($authorizationChallenge->getAuthorizationExpiration() !== null) {
                if ($closestAuthorizationExpiration === null || $closestAuthorizationExpiration > $authorizationChallenge->getAuthorizationExpiration()) {
                    $closestAuthorizationExpiration = $authorizationChallenge->getAuthorizationExpiration();
                }
            }
            if (!in_array($authorizationChallenge->getAuthorizationStatus(), $authorizationStatuses, true)) {
                $authorizationStatuses[] = $authorizationChallenge->getAuthorizationStatus();
            }
        }
        if ($authorizationStatuses === [AuthorizationChallenge::AUTHORIZATIONSTATUS_VALID]) {
            $status = Order::STATUS_READY;
        } elseif (array_diff($authorizationStatuses, [AuthorizationChallenge::AUTHORIZATIONSTATUS_PENDING, AuthorizationChallenge::AUTHORIZATIONSTATUS_VALID]) === []) {
            $status = Order::STATUS_PENDING;
        } else {
            $status = Order::STATUS_INVALID;
        }
        $order
            ->setExpiration($closestAuthorizationExpiration)
            ->setStatus($status)
        ;
    }

    /**
     * @param \Acme\Entity\Order $order
     * @param \Acme\Protocol\Response[] $childResponses
     * @param null|array $authorizationUrls
     *
     * @throws \Acme\Exception\Exception
     */
    protected function unserializeAuthorizationChallenges(Order $order, array $childResponses, array $authorizationUrls = null)
    {
        foreach ($childResponses as $index => $childResponse) {
            $authorizationUrl = $childResponse->getLocation();
            if ($authorizationUrl === '') {
                $authorizationUrl = array_get($authorizationUrls ?: [], $index);
                if ($authorizationUrl === null) {
                    throw new RuntimeException(t('Unable to find the authorization URL'));
                }
            }
            $authorizationChallenge = $this->unserializeAuthorizationChallenge($order, $authorizationUrl, $childResponse->getData());
            $order->getAuthorizationChallenges()->add($authorizationChallenge);
        }
    }

    /**
     * @param \Acme\Entity\Order $order
     * @param string $authorizationUrl
     * @param array $authorization
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Entity\AuthorizationChallenge
     */
    protected function unserializeAuthorizationChallenge(Order $order, $authorizationUrl, array $authorization)
    {
        $domain = $this->detectAuthorizationChallengeDomain($order, $authorization);
        $challenges = array_get($authorization, 'challenges');
        $challengeIndex = $this->getChallengeIndex($domain, $challenges, array_get($authorization, 'combinations'));
        $challenge = $challenges[$challengeIndex];
        $authorizationChallenge = AuthorizationChallenge::create($order, $domain)
            ->setAuthorizationUrl($authorizationUrl)
            ->setChallengeUrl(array_get($challenge, 'url', array_get($challenge, 'uri')))
            ->setChallengeToken($this->arrayGetNonEmptyString($challenge, 'token'))
        ;
        if ($authorizationChallenge->getChallengeUrl() === '') {
            throw new RuntimeException(t('Detected challenge without URL'));
        }
        $authorizationChallenge->setChallengeAuthorizationKey($this->crypto->generateChallengeAuthorizationKey($order->getCertificate()->getAccount(), $authorizationChallenge->getChallengeToken()));
        $this->updateAuthorizationChallenge($authorizationChallenge, $authorization, $challenge);

        return $authorizationChallenge;
    }

    /**
     * @param \Acme\Entity\Order $order
     * @param array $authorization
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Entity\Domain
     */
    protected function detectAuthorizationChallengeDomain(Order $order, array $authorization)
    {
        $identifierType = $this->arrayGetNonEmptyString($authorization, 'identifier.type');
        if ($identifierType !== 'dns') {
            throw new RuntimeException(t('Unsupported identifier type: %s', $identifierType));
        }
        $punycode = $this->arrayGetNonEmptyString($authorization, 'identifier.value');
        $isWildcard = (bool) array_get($authorization, 'wildcard');
        foreach ($order->getCertificate()->getDomains() as $certificateDomain) {
            $domain = $certificateDomain->getDomain();
            if ($domain->getPunycode() === $punycode && $domain->isWildcard() === $isWildcard) {
                return $domain;
            }
        }
        throw new RuntimeException(t('Unable to find the domain %s', ($isWildcard ? '*.' : '') . $punycode));
    }

    /**
     * @param \Acme\Entity\Domain $domain
     * @param array $challenges
     * @param array|null $combinations
     *
     * @throws \Acme\Exception\Exception
     *
     * return int|null
     */
    protected function getChallengeIndex(Domain $domain, array $challenges, array $combinations = null)
    {
        $domainChallengeType = $this->challengeTypeManager->getChallengeByHandle($domain->getChallengeTypeHandle());
        if ($domainChallengeType === null) {
            throw new RuntimeException(t('Invalid challenge type set for domain %s', $domain->getHostDisplayName()));
        }
        $availableTypes = [];
        $result = null;
        foreach ($challenges as $challengeIndex => $challenge) {
            if ($combinations !== null && !in_array([(int) $challengeIndex], $combinations, true)) {
                continue;
            }
            $challengeTypeID = $this->arrayGetNonEmptyString($challenge, 'type');
            $availableTypes[] = $challengeTypeID;
            if ($challengeTypeID !== $domainChallengeType->getAcmeTypeName()) {
                continue;
            }
            if ($result !== null) {
                throw new RuntimeException(t('Multiple challenges found for type %s', $challengeTypeID));
            }
            $result = $challengeIndex;
        }
        if ($result === null) {
            throw new RuntimeException(
                t("There's no compatible authorization method found for domain %s.", $domain->getHostDisplayName())
                . "\n" .
                t('Wanted authorization method: %s', $domainChallengeType->getAcmeTypeName())
                . "\n" .
                t('Available authorization methods: %s', implode(', ', $availableTypes))
            );
        }

        return $result;
    }

    /**
     * @param array $data
     * @param string $key
     * @param int|null $maxLength
     *
     * @throws \Acme\Exception\Exception
     *
     * @return string
     */
    protected function arrayGetNonEmptyString(array $data, $key, $maxLength = null)
    {
        $value = array_get($data, $key);
        if (!is_string($value) || $value === '') {
            throw new RuntimeException(t('Expected string at key %s', $key));
        }
        if ($maxLength !== null && strlen($value) > $maxLength) {
            throw new RuntimeException(t('String at key %s is too long', $key));
        }

        return $value;
    }
}
