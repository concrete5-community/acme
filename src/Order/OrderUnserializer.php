<?php

namespace Acme\Order;

use Acme\ChallengeType\ChallengeTypeManager;
use Acme\Crypto\Engine;
use Acme\Crypto\Hash;
use Acme\Crypto\PrivateKey;
use Acme\Entity\Account;
use Acme\Entity\AuthorizationChallenge;
use Acme\Entity\Certificate;
use Acme\Entity\Domain;
use Acme\Entity\Order;
use Acme\Exception\RuntimeException;
use Acme\Protocol\Response;
use Acme\Service\Base64EncoderTrait;
use Acme\Service\DateTimeParser;
use Acme\Service\JsonEncoderTrait;

defined('C5_EXECUTE') or die('Access Denied.');

final class OrderUnserializer
{
    use Base64EncoderTrait;

    use JsonEncoderTrait;

    /**
     * @var \Acme\Service\DateTimeParser
     */
    private $dateTimeParser;

    /**
     * @var \Acme\ChallengeType\ChallengeTypeManager
     */
    private $challengeTypeManager;

    /**
     * @var int
     */
    private $engineID;

    /**
     * @param int|null $engineID The value of one of the Acme\Crypto\Engine constants
     */
    public function __construct(DateTimeParser $dateTimeParser, ChallengeTypeManager $challengeTypeManager, $engineID = null)
    {
        $this->dateTimeParser = $dateTimeParser;
        $this->challengeTypeManager = $challengeTypeManager;
        $this->engineID = $engineID === null ? Engine::get() : $engineID;
    }

    /**
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

    public function updateAuthorizationChallenge(AuthorizationChallenge $authorizationChallenge, array $authorization, array $challenge)
    {
        $authorizationChallenge
            ->setAuthorizationExpiration($this->dateTimeParser->toDateTime(array_get($authorization, 'expires')))
            ->setAuthorizationStatus($this->arrayGetNonEmptyString($authorization, 'status'))
            ->setChallengeStatus($this->arrayGetNonEmptyString($challenge, 'status'))
            ->setChallengeErrorMessage(array_get($challenge, 'error.detail'))
        ;
    }

    public function updateMainOrderRecord(Order $order, array $data)
    {
        $order
            ->setStatus($this->arrayGetNonEmptyString($data, 'status'))
            ->setExpiration($this->dateTimeParser->toDateTime(array_get($data, 'expires')))
            ->setCertificateUrl(array_get($data, 'certificate', ''))
        ;
    }

    /**
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
     * @param \Acme\Protocol\Response[] $childResponses
     *
     * @throws \Acme\Exception\Exception
     */
    private function unserializeAuthorizationChallenges(Order $order, array $childResponses, array $authorizationUrls = null)
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
     * @param string $authorizationUrl
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Entity\AuthorizationChallenge
     */
    private function unserializeAuthorizationChallenge(Order $order, $authorizationUrl, array $authorization)
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
        $authorizationChallenge->setChallengeAuthorizationKey($this->generateChallengeAuthorizationKey($order->getCertificate()->getAccount(), $authorizationChallenge->getChallengeToken()));
        $this->updateAuthorizationChallenge($authorizationChallenge, $authorization, $challenge);

        return $authorizationChallenge;
    }

    /**
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Entity\Domain
     */
    private function detectAuthorizationChallengeDomain(Order $order, array $authorization)
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
     * @throws \Acme\Exception\Exception
     *
     * return int|null
     */
    private function getChallengeIndex(Domain $domain, array $challenges, array $combinations = null)
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
                t("There's no compatible authorization method for domain %s.", $domain->getHostDisplayName())
                . "\n" .
                t('Wanted authorization method: %s', $domainChallengeType->getAcmeTypeName())
                . "\n" .
                t('Available authorization methods: %s', implode(', ', $availableTypes))
            );
        }

        return $result;
    }

    /**
     * @param string $key
     * @param int|null $maxLength
     *
     * @throws \Acme\Exception\Exception
     *
     * @return string
     */
    private function arrayGetNonEmptyString(array $data, $key, $maxLength = null)
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

    /**
     * Generate the authorization challenge authorization key.
     *
     * @param string $challengeToken
     *
     * @throws \Acme\Exception\Codec\Base64EncodingException when we couldn't build a base-64 representation
     * @throws \Acme\Exception\KeyPair\MalformedPrivateKeyException when the private key is malformed
     * @throws \Acme\Exception\Codec\JsonEncodingException when we couldn't convert $data to json
     *
     * @return string
     */
    private function generateChallengeAuthorizationKey(Account $account, $challengeToken)
    {
        $privateKey = PrivateKey::fromString($account->getPrivateKey(), $this->engineID);

        return $challengeToken . '.' . $this->getPrivateKeyThumbprint($privateKey);
    }

    /**
     * Get the thumbprint of a private key.
     *
     * @throws \Acme\Exception\Codec\Base64EncodingException when we couldn't build a base-64 representation
     * @throws \Acme\Exception\KeyPair\MalformedPrivateKeyException when the private key is malformed
     * @throws \Acme\Exception\Codec\JsonEncodingException when we couldn't convert $data to json
     *
     * @return string
     */
    private function getPrivateKeyThumbprint(PrivateKey $privateKey)
    {
        $jwkJson = $this->toJson($privateKey->getJwk());
        $hasher = new Hash('sha256', $this->engineID);
        $hash = $hasher->hash($jwkJson);

        return $this->toBase64UrlSafe($hash);
    }
}
