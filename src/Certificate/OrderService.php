<?php

namespace Acme\Certificate;

use Acme\ChallengeType\ChallengeTypeManager;
use Acme\Entity\Account;
use Acme\Entity\AuthorizationChallenge;
use Acme\Entity\Certificate;
use Acme\Entity\Domain;
use Acme\Entity\Order;
use Acme\Exception\RuntimeException;
use Acme\Exception\UnrecognizedProtocolVersionException;
use Acme\Order\OrderUnserializer;
use Acme\Protocol\Communicator;
use Acme\Protocol\Version;
use Acme\Security\Crypto;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class OrderService
{
    /**
     * @var \Acme\Protocol\Communicator
     */
    protected $communicator;

    /**
     * @var \Acme\Order\OrderUnserializer
     */
    protected $orderUnserializer;

    /**
     * @var \Acme\ChallengeType\ChallengeTypeManager
     */
    protected $challengeTypeManager;

    /**
     * @var \Acme\Certificate\CsrGenerator
     */
    protected $csrGenerator;

    /**
     * @var \Acme\Security\Crypto
     */
    protected $crypto;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * @param \Acme\Protocol\Communicator $communicator
     * @param \Acme\Order\OrderUnserializer $orderUnserializer
     * @param \Acme\ChallengeType\ChallengeTypeManager $challengeTypeManager
     * @param \Acme\Certificate\CertificateInfoCreator $certificateInfoCreator
     * @param \Acme\Security\Crypto $crypto
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param CsrGenerator $csrGenerator
     */
    public function __construct(Communicator $communicator, OrderUnserializer $orderUnserializer, ChallengeTypeManager $challengeTypeManager, CertificateInfoCreator $certificateInfoCreator, CsrGenerator $csrGenerator, Crypto $crypto, EntityManagerInterface $em)
    {
        $this->communicator = $communicator;
        $this->orderUnserializer = $orderUnserializer;
        $this->challengeTypeManager = $challengeTypeManager;
        $this->certificateInfoCreator = $certificateInfoCreator;
        $this->csrGenerator = $csrGenerator;
        $this->crypto = $crypto;
        $this->em = $em;
    }

    /**
     * Create a new order for a new certificate (ACME v2 only).
     *
     * @param \Acme\Entity\Certificate $certificate
     *
     * @return \Acme\Entity\Order
     */
    public function createOrder(Certificate $certificate)
    {
        $mainResponse = $this->communicator->send(
            $certificate->getAccount(),
            'POST',
            $certificate->getAccount()->getServer()->getNewOrderUrl(),
            $this->getCreateOrderPayload($certificate),
            [201]
        );

        $childResponses = [];
        foreach (array_get($mainResponse->getData(), 'authorizations', []) as $authorizationUrl) {
            $childResponses[] = $this->communicator->send(
                $certificate->getAccount(),
                'POST',
                $authorizationUrl,
                null,
                [200]
            );
        }

        return $this->orderUnserializer->unserializeOrder($certificate, $mainResponse, $childResponses);
    }

    /**
     * Create a set of domain authorizations (ACME v1 and maybe ACME v2).
     *
     * @param \Acme\Entity\Certificate $certificate
     *
     * @return \Acme\Entity\Order
     */
    public function createAuthorizationChallenges(Certificate $certificate)
    {
        $authorizationResponses = [];
        foreach ($certificate->getDomains() as $certificateDomain) {
            $authorizationResponses[] = $this->communicator->send(
                $certificate->getAccount(),
                'POST',
                $certificate->getAccount()->getServer()->getNewAuthorizationUrl(),
                $this->getAuthorizationChallengePayload($certificateDomain->getDomain()),
                [201]
            );
        }

        return $this->orderUnserializer->unserializeAuthorizationRequests($certificate, $authorizationResponses);
    }

    /**
     * Start the authorization challenges (if not already started).
     *
     * @param \Acme\Entity\Order $order it must be already persisted
     */
    public function startAuthorizationChallenges(Order $order)
    {
        $refreshGlobalState = false;
        foreach ($order->getAuthorizationChallenges() as $authorizationChallenge) {
            if ($authorizationChallenge->getAuthorizationStatus() === AuthorizationChallenge::AUTHORIZATIONSTATUS_PENDING && $authorizationChallenge->getChallengeStatus() === AuthorizationChallenge::CHALLENGESTATUS_PENDING) {
                $this->startAuthorizationChallenge($authorizationChallenge);
                $refreshGlobalState = true;
            }
        }
        if ($refreshGlobalState) {
            switch ($order->getType()) {
                case Order::TYPE_AUTHORIZATION:
                    $this->orderUnserializer->updateMainAuthorizationSetRecord($order);
                    break;
                case Order::TYPE_ORDER:
                    $this->orderUnserializer->updateMainOrderRecord($order, $this->fetchOrderData($order));
                    break;
            }
            $this->em->flush($order);
        }
    }

    /**
     * Refresh the authorization states.
     *
     * @param \Acme\Entity\Order $order it must be already persisted
     */
    public function refresh(Order $order)
    {
        foreach ($order->getAuthorizationChallenges() as $authorizationChallenge) {
            $this->orderUnserializer->updateAuthorizationChallenge(
                $authorizationChallenge,
                $this->fetchAuthorizationData($authorizationChallenge),
                $this->fetchChallengeData($authorizationChallenge)
            );
        }
        switch ($order->getType()) {
            case Order::TYPE_AUTHORIZATION:
                $this->orderUnserializer->updateMainAuthorizationSetRecord($order);
                break;
            case Order::TYPE_ORDER:
                $this->orderUnserializer->updateMainOrderRecord($order, $this->fetchOrderData($order));
                break;
        }
        $this->em->flush($order);
    }

    /**
     * Stop the authorization challenges.
     *
     * @param \Acme\Entity\Order $order it must be already persisted
     */
    public function stopAuthorizationChallenges(Order $order)
    {
        foreach ($order->getAuthorizationChallenges() as $authorizationChallenge) {
            $this->stopAuthorizationChallenge($authorizationChallenge);
        }
    }

    /**
     * Finalize an ACME v2 certificate order, by requesting the generation of the certificate.
     *
     * @param \Acme\Entity\Order $order
     *
     * @throws \Acme\Exception\Exception
     */
    public function finalizeOrder(Order $order)
    {
        $resetCsr = false;
        $certificate = $order->getCertificate();
        try {
            if ($certificate->getCsr() === '') {
                $certificate->setCsr($this->csrGenerator->generateCsrFromCertificate($certificate));
                $resetCsr = true;
            }
            $response = $this->communicator->send(
                $certificate->getAccount(),
                'POST',
                $order->getFinalizeUrl(),
                [
                    'csr' => $this->crypto->toBase64($this->crypto->pemToDer($certificate->getCsr())),
                ],
                [200]
            );
            $this->orderUnserializer->updateMainOrderRecord($order, $response->getData());
            $this->em->flush($order);
            if ($resetCsr) {
                $this->em->flush($certificate);
                $resetCsr = false;
            }
        } finally {
            if ($resetCsr) {
                $certificate->setCsr('');
            }
        }
    }

    /**
     * @param \Acme\Entity\Certificate $certificate
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Protocol\Response Possible codes: 201 (new certificate available), 403 (new authorization required)
     */
    public function callAcme01NewCert(Certificate $certificate)
    {
        $resetCsr = false;
        try {
            $account = $certificate->getAccount();
            $server = $account->getServer();
            if ($certificate->getCsr() === '') {
                $certificate->setCsr($this->csrGenerator->generateCsrFromCertificate($certificate));
                $resetCsr = true;
            }
            $response = $this->communicator->send(
                $account,
                'POST',
                $server->getNewCertificateUrl(),
                [
                    'resource' => 'new-cert',
                    'csr' => $this->crypto->toBase64($this->crypto->pemToDer($certificate->getCsr())),
                ],
                [201, 403]
            );
            if ($resetCsr) {
                if ($response->getCode() < 200 || $response->getCode() >= 300) {
                    $certificate->setCsr('');
                } else {
                    $this->em->flush($certificate);
                }
                $resetCsr = false;
            }

            return $response;
        } finally {
            if ($resetCsr) {
                $certificate->setCsr('');
            }
        }
    }

    /**
     * @param \Acme\Entity\Account $account
     * @param string $url
     * @param bool $retryOnEmptyResponse
     *
     * @return string
     */
    public function downloadActualCertificate(Account $account, $url, $retryOnEmptyResponse = false)
    {
        switch ($account->getServer()->getProtocolVersion()) {
            case Version::ACME_01:
                $method = 'GET';
                break;
            case Version::ACME_02:
                $method = 'POST';
                break;
            default:
                throw UnrecognizedProtocolVersionException::create($account->getServer()->getProtocolVersion());
        }
        for ($retry = 0; $retry < 2; $retry++) {
            $response = $this->communicator->send(
                $account,
                $method,
                $url,
                null,
                [200]
            );
            if (!empty($response->getData()) || !$retryOnEmptyResponse) {
                break;
            }
            pause(2);
        }
        $result = $response->getData();
        if (!is_string($result)) {
            throw new RuntimeException(t('Error downloading the certificate: expected a string, got %s', gettype($response)));
        }
        if ($result === '') {
            throw new RuntimeException(t('Error downloading the certificate: empty result'));
        }

        return $result;
    }

    /**
     * @param \Acme\Entity\Certificate $certificate
     *
     * @throws \Acme\Exception\Exception
     *
     * @return array
     */
    protected function getCreateOrderPayload(Certificate $certificate)
    {
        $result = ['identifiers' => []];
        foreach ($certificate->getDomains() as $certificateDomain) {
            $result['identifiers'][] = [
                'type' => 'dns',
                'value' => $certificateDomain->getDomain()->getPunycodeDisplayName(),
            ];
        }

        return $result;
    }

    /**
     * @param \Acme\Entity\Domain $domain
     *
     * @throws \Acme\Exception\Exception
     *
     * @return array
     */
    protected function getAuthorizationChallengePayload(Domain $domain)
    {
        $result = [
            'identifier' => [
                'type' => 'dns',
                'value' => $domain->getPunycode(),
            ],
        ];
        switch ($domain->getAccount()->getServer()->getProtocolVersion()) {
            case Version::ACME_01:
                if ($domain->isWildcard()) {
                    throw new RuntimeException(t('ACME v1 does not support wildcard domains'));
                }

                return $result + ['resource' => 'new-authz'];
            case Version::ACME_02:
                if ($domain->isWildcard()) {
                    $result['wildcard'] = true;
                }

                return $result;
            default:
                throw UnrecognizedProtocolVersionException::create($domain->getAccount()->getServer()->getProtocolVersion());
        }
    }

    /**
     * @param \Acme\Entity\AuthorizationChallenge $authorizationChallenge
     *
     * @throws \Acme\Exception\Exception
     */
    protected function startAuthorizationChallenge(AuthorizationChallenge $authorizationChallenge)
    {
        $domain = $authorizationChallenge->getDomain();
        $challengeType = $this->challengeTypeManager->getChallengeByHandle($domain->getChallengeTypeHandle());
        if ($challengeType === null) {
            throw new RuntimeException(t('Invalid challenge type set for domain %s', $domain->getHostDisplayName()));
        }
        $payload = $this->getStartAuthorizationChallengePayload($authorizationChallenge);
        $revertStartedStatus = false;
        if ($authorizationChallenge->isChallengeStarted() !== true) {
            $challengeType->beforeChallenge($authorizationChallenge);
            $revertStartedStatus = true;
        }
        try {
            $startException = null;
            try {
                $authorizationChallenge->setIsChallengeStarted(true);
                $this->em->flush($authorizationChallenge);
                $challenge = $this->communicator->send(
                    $domain->getAccount(),
                    'POST',
                    $authorizationChallenge->getChallengeUrl(),
                    $payload,
                    [200, 202]
                )->getData();
            } catch (Exception $x) {
                $startException = $x;
            } catch (Throwable $x) {
                $startException = $x;
            }
            if ($startException !== null) {
                $challenge = $this->fetchChallengeData($authorizationChallenge);
            }
            $authorization = $this->fetchAuthorizationData($authorizationChallenge, $authorizationChallenge->getAuthorizationUrl());
            $this->orderUnserializer->updateAuthorizationChallenge($authorizationChallenge, $authorization, $challenge);
            if ($startException !== null && $authorizationChallenge->getChallengeStatus() === $authorizationChallenge::CHALLENGESTATUS_PENDING) {
                throw $startException;
            }
            $this->em->flush($authorizationChallenge);
            $revertStartedStatus = false;
        } finally {
            if ($revertStartedStatus) {
                try {
                    $challengeType->afterChallenge($authorizationChallenge);
                } catch (Exception $foo) {
                } catch (Throwable $foo) {
                }
                try {
                    $authorizationChallenge->setIsChallengeStarted(false);
                    $this->em->flush($authorizationChallenge);
                } catch (Exception $foo) {
                } catch (Throwable $foo) {
                }
            }
        }
    }

    /**
     * @param \Acme\Entity\AuthorizationChallenge $authorizationChallenge
     *
     * @throws \Acme\Exception\Exception
     *
     * @return array|null
     */
    protected function getStartAuthorizationChallengePayload(AuthorizationChallenge $authorizationChallenge)
    {
        $account = $authorizationChallenge->getDomain()->getAccount();
        switch ($account->getServer()->getProtocolVersion()) {
            case Version::ACME_01:
                return [
                    'resource' => 'challenge',
                    'keyAuthorization' => $authorizationChallenge->getChallengeAuthorizationKey(),
                ];
            case Version::ACME_02:
                return [];
            default:
                throw UnrecognizedProtocolVersionException::create($account->getServer()->getProtocolVersion());
        }
    }

    /**
     * Stop an authorization challenge (if it was started).
     *
     * @param \Acme\Entity\AuthorizationChallenge $authorizationChallenge
     */
    protected function stopAuthorizationChallenge(AuthorizationChallenge $authorizationChallenge)
    {
        if ($authorizationChallenge->isChallengeStarted()) {
            try {
                $domain = $authorizationChallenge->getDomain();
                $challengeType = $this->challengeTypeManager->getChallengeByHandle($domain->getChallengeTypeHandle());
                if ($challengeType !== null) {
                    $challengeType->afterChallenge($authorizationChallenge);
                }
            } catch (Exception $foo) {
            } catch (Throwable $foo) {
            }
            $authorizationChallenge->setIsChallengeStarted(false);
            $this->em->flush($authorizationChallenge);
        }
    }

    /**
     * @param \Acme\Entity\Order $order
     *
     * @throws \Acme\Exception\Exception
     *
     * @return array
     */
    protected function fetchOrderData(Order $order)
    {
        return $this->fetchData($order->getCertificate()->getAccount(), $order->getOrderUrl());
    }

    /**
     * @param \Acme\Entity\AuthorizationChallenge $authorizationChallenge
     *
     * @throws \Acme\Exception\Exception
     *
     * @return array
     */
    protected function fetchAuthorizationData(AuthorizationChallenge $authorizationChallenge)
    {
        return $this->fetchData($authorizationChallenge->getDomain()->getAccount(), $authorizationChallenge->getAuthorizationUrl());
    }

    /**
     * @param \Acme\Entity\AuthorizationChallenge $authorizationChallenge
     *
     * @throws \Acme\Exception\Exception
     *
     * @return array
     */
    protected function fetchChallengeData(AuthorizationChallenge $authorizationChallenge)
    {
        return $this->fetchData($authorizationChallenge->getDomain()->getAccount(), $authorizationChallenge->getChallengeUrl());
    }

    /**
     * @param \Acme\Entity\Account $account
     * @param string $url
     *
     * @throws \Acme\Exception\Exception
     *
     * @return array
     */
    protected function fetchData(Account $account, $url)
    {
        $response = $this->communicator->send(
            $account,
            $this->getFetchDataMethod($account),
            $url,
            null,
            [200, 202]
        );

        return $response->getData();
    }

    /**
     * @param \Acme\Entity\Account $account
     *
     * @throws \Acme\Exception\Exception
     *
     * @return string
     */
    protected function getFetchDataMethod(Account $account)
    {
        switch ($account->getServer()->getProtocolVersion()) {
            case Version::ACME_01:
                return 'GET';
            case Version::ACME_02:
                return 'POST';
            default:
                throw UnrecognizedProtocolVersionException::create($account->getServer()->getProtocolVersion());
        }
    }
}
