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
use Acme\Protocol\Response;
use Acme\Protocol\Version;
use Acme\Service\Base64EncoderTrait;
use Acme\Service\PemDerConversionTrait;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

final class OrderService
{
    use Base64EncoderTrait;

    use PemDerConversionTrait;

    /**
     * @var \Acme\Protocol\Communicator
     */
    private $communicator;

    /**
     * @var \Acme\Order\OrderUnserializer
     */
    private $orderUnserializer;

    /**
     * @var \Acme\ChallengeType\ChallengeTypeManager
     */
    private $challengeTypeManager;

    /**
     * @var \Acme\Certificate\CsrGenerator
     */
    private $csrGenerator;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    public function __construct(Communicator $communicator, OrderUnserializer $orderUnserializer, ChallengeTypeManager $challengeTypeManager, CertificateInfoCreator $certificateInfoCreator, CsrGenerator $csrGenerator, EntityManagerInterface $em)
    {
        $this->communicator = $communicator;
        $this->orderUnserializer = $orderUnserializer;
        $this->challengeTypeManager = $challengeTypeManager;
        $this->certificateInfoCreator = $certificateInfoCreator;
        $this->csrGenerator = $csrGenerator;
        $this->em = $em;
    }

    /**
     * Create a new order for a new certificate (ACME v2 only).
     *
     * @return \Acme\Entity\Order
     */
    public function createOrder(Certificate $certificate, LoggerInterface $logger)
    {
        $logger->debug(t('Creating the order for a new certificate'));
        $mainResponse = $this->communicator->send(
            $certificate->getAccount(),
            'POST',
            $certificate->getAccount()->getServer()->getNewOrderUrl(),
            $this->getCreateOrderPayload($certificate),
            [201]
        );
        $this->logResponse($logger, $mainResponse);
        $childResponses = [];
        foreach (array_get($mainResponse->getData(), 'authorizations', []) as $authorizationUrl) {
            $logger->debug(t('Requesting an authorization at URL %s', $authorizationUrl));
            $childResponse = $this->communicator->send(
                $certificate->getAccount(),
                'POST',
                $authorizationUrl,
                null,
                [200]
            );
            $this->logResponse($logger, $childResponse);
            $childResponses[] = $childResponse;
        }

        return $this->orderUnserializer->unserializeOrder($certificate, $mainResponse, $childResponses);
    }

    /**
     * Create a set of domain authorizations (ACME v1 only).
     *
     * @return \Acme\Entity\Order
     */
    public function createAuthorizationChallenges(Certificate $certificate, LoggerInterface $logger)
    {
        $authorizationResponses = [];
        foreach ($certificate->getDomains() as $certificateDomain) {
            $logger->debug(t('Requesting the authorization challenge for the domain %s', $certificateDomain->getDomain()->getHostDisplayName()));
            $authorizationResponse = $this->communicator->send(
                $certificate->getAccount(),
                'POST',
                $certificate->getAccount()->getServer()->getNewAuthorizationUrl(),
                $this->getAuthorizationChallengePayload($certificateDomain->getDomain()),
                [201]
            );
            $this->logResponse($logger, $authorizationResponse);
            $authorizationResponses[] = $authorizationResponse;
        }

        return $this->orderUnserializer->unserializeAuthorizationRequests($certificate, $authorizationResponses);
    }

    /**
     * Start the authorization challenges (if not already started).
     *
     * @param \Acme\Entity\Order $order it must be already persisted
     *
     * @return bool return TRUE if all the challenges are ready, FALSE if we have to retry to start the challenges later
     */
    public function startAuthorizationChallenges(Order $order, LoggerInterface $logger)
    {
        $result = true;
        $refreshGlobalState = false;
        foreach ($order->getAuthorizationChallenges() as $authorizationChallenge) {
            if ($authorizationChallenge->getAuthorizationStatus() === AuthorizationChallenge::AUTHORIZATIONSTATUS_PENDING && $authorizationChallenge->getChallengeStatus() === AuthorizationChallenge::CHALLENGESTATUS_PENDING) {
                $started = $this->startAuthorizationChallenge($authorizationChallenge, $logger);
                if ($started === true) {
                    $refreshGlobalState = true;
                } elseif ($started === false) {
                    $result = false;
                }
            }
        }
        if ($refreshGlobalState) {
            switch ($order->getType()) {
                case Order::TYPE_AUTHORIZATION:
                    $this->orderUnserializer->updateMainAuthorizationSetRecord($order);
                    break;
                case Order::TYPE_ORDER:
                    $this->orderUnserializer->updateMainOrderRecord($order, $this->fetchOrderData($order, $logger));
                    break;
            }
            $this->em->flush($order);
        }

        return $result;
    }

    /**
     * Refresh the authorization states.
     *
     * @param \Acme\Entity\Order $order it must be already persisted
     */
    public function refresh(Order $order, LoggerInterface $logger)
    {
        foreach ($order->getAuthorizationChallenges() as $authorizationChallenge) {
            $this->orderUnserializer->updateAuthorizationChallenge(
                $authorizationChallenge,
                $this->fetchAuthorizationData($authorizationChallenge, $logger),
                $this->fetchChallengeData($authorizationChallenge, $logger)
            );
        }
        switch ($order->getType()) {
            case Order::TYPE_AUTHORIZATION:
                $this->orderUnserializer->updateMainAuthorizationSetRecord($order);
                break;
            case Order::TYPE_ORDER:
                $this->orderUnserializer->updateMainOrderRecord($order, $this->fetchOrderData($order, $logger));
                break;
        }
        $this->em->flush($order);
    }

    /**
     * Stop the authorization challenges.
     *
     * @param \Acme\Entity\Order $order it must be already persisted
     */
    public function disposeAuthorizationChallenges(Order $order, LoggerInterface $logger)
    {
        foreach ($order->getAuthorizationChallenges() as $authorizationChallenge) {
            $this->disposeAuthorizationChallenge($authorizationChallenge, $logger);
        }
    }

    /**
     * Finalize an ACME v2 certificate order, by requesting the generation of the certificate.
     *
     * @throws \Acme\Exception\Exception
     */
    public function finalizeOrder(Order $order, LoggerInterface $logger)
    {
        $resetCsr = false;
        $certificate = $order->getCertificate();
        try {
            if ($certificate->getCsr() === '') {
                $logger->debug(t('Generating CSR'));
                $certificate->setCsr($this->csrGenerator->generateCsrFromCertificate($certificate));
                $resetCsr = true;
            }
            $logger->debug(t('Finalizing order'));
            $response = $this->communicator->send(
                $certificate->getAccount(),
                'POST',
                $order->getFinalizeUrl(),
                [
                    'csr' => $this->toBase64UrlSafe($this->convertPemToDer($certificate->getCsr())),
                ],
                [200]
            );
            $this->logResponse($logger, $response);
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
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Protocol\Response Possible codes: 201 (new certificate available), 403 (new authorization required)
     */
    public function callAcme01NewCert(Certificate $certificate, LoggerInterface $logger)
    {
        $resetCsr = false;
        try {
            $account = $certificate->getAccount();
            $server = $account->getServer();
            if ($certificate->getCsr() === '') {
                $logger->debug(t('Generating CSR'));
                $certificate->setCsr($this->csrGenerator->generateCsrFromCertificate($certificate));
                $resetCsr = true;
            }
            $logger->debug(t('Requesting a new certificate'));
            $response = $this->communicator->send(
                $account,
                'POST',
                $server->getNewCertificateUrl(),
                [
                    'resource' => 'new-cert',
                    'csr' => $this->toBase64UrlSafe($this->convertPemToDer($certificate->getCsr())),
                ],
                [201, 403]
            );
            $this->logResponse($logger, $response);
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
     * @param string $url
     * @param bool $retryOnEmptyResponse
     *
     * @return string
     */
    public function downloadActualCertificate(Account $account, $url, $retryOnEmptyResponse, LoggerInterface $logger)
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
            $logger->debug(t('Downloading the certificate'));
            $response = $this->communicator->send(
                $account,
                $method,
                $url,
                null,
                [200]
            );
            $this->logResponse($logger, $response);
            if (!empty($response->getData()) || !$retryOnEmptyResponse) {
                break;
            }
            $logger->debug(t("The download failed: we'll retry in a while."));
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
     * @throws \Acme\Exception\Exception
     *
     * @return array
     */
    private function getCreateOrderPayload(Certificate $certificate)
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
     * @throws \Acme\Exception\Exception
     *
     * @return array
     */
    private function getAuthorizationChallengePayload(Domain $domain)
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
     * @throws \Acme\Exception\Exception
     *
     * @return bool|null return NULL if no operation is needed, TRUE in case of success, FALSE if we have to retry to start the challenge later
     */
    private function startAuthorizationChallenge(AuthorizationChallenge $authorizationChallenge, LoggerInterface $logger)
    {
        if ($authorizationChallenge->getAuthorizationStatus() !== AuthorizationChallenge::AUTHORIZATIONSTATUS_PENDING || $authorizationChallenge->getChallengeStatus() !== AuthorizationChallenge::CHALLENGESTATUS_PENDING) {
            return null;
        }
        $domain = $authorizationChallenge->getDomain();
        $logger->debug(t('Starting the autorization for the domain %s', $domain->getHostDisplayName()));
        $challengeType = $this->challengeTypeManager->getChallengeByHandle($domain->getChallengeTypeHandle());
        if ($challengeType === null) {
            throw new RuntimeException(t('Invalid challenge type set for domain %s', $domain->getHostDisplayName()));
        }
        $result = false;
        $revertStartedStatus = false;
        try {
            if ($authorizationChallenge->isChallengePrepared() !== true) {
                $challengeType->beforeChallenge($authorizationChallenge, $logger);
                $revertStartedStatus = true;
            }
            if ($challengeType->isReadyForChallenge($authorizationChallenge, $logger)) {
                $payload = $this->getStartAuthorizationChallengePayload($authorizationChallenge);
                $startException = null;
                try {
                    $authorizationChallenge->setIsChallengePrepared(true);
                    $this->em->flush($authorizationChallenge);
                    $logger->debug(t('Asking the ACME server to start the challenge'));
                    $response = $this->communicator->send(
                        $domain->getAccount(),
                        'POST',
                        $authorizationChallenge->getChallengeUrl(),
                        $payload,
                        [200, 202]
                    );
                    $this->logResponse($logger, $response);
                    $challenge = $response->getData();
                } catch (Exception $x) {
                    $startException = $x;
                } catch (Throwable $x) {
                    $startException = $x;
                }
                if ($startException !== null) {
                    $challenge = $this->fetchChallengeData($authorizationChallenge, $logger);
                }
                $authorization = $this->fetchAuthorizationData($authorizationChallenge, $logger);
                $this->orderUnserializer->updateAuthorizationChallenge($authorizationChallenge, $authorization, $challenge);
                if ($startException !== null && $authorizationChallenge->getChallengeStatus() === $authorizationChallenge::CHALLENGESTATUS_PENDING) {
                    throw $startException;
                }
                $result = true;
            }
            $this->em->flush($authorizationChallenge);
            $revertStartedStatus = false;
        } finally {
            if ($revertStartedStatus) {
                try {
                    $challengeType->afterChallenge($authorizationChallenge, $logger);
                } catch (Exception $foo) {
                } catch (Throwable $foo) {
                }
                try {
                    $authorizationChallenge->setIsChallengePrepared(false);
                    $this->em->flush($authorizationChallenge);
                } catch (Exception $foo) {
                } catch (Throwable $foo) {
                }
            }
        }

        return $result;
    }

    /**
     * @throws \Acme\Exception\Exception
     *
     * @return array|null
     */
    private function getStartAuthorizationChallengePayload(AuthorizationChallenge $authorizationChallenge)
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
     */
    private function disposeAuthorizationChallenge(AuthorizationChallenge $authorizationChallenge, LoggerInterface $logger)
    {
        if (!$authorizationChallenge->isChallengePrepared()) {
            return;
        }
        $logger->debug(t('Cleanup after the challenge for the domain %s', $authorizationChallenge->getDomain()->getHostDisplayName()));
        try {
            $domain = $authorizationChallenge->getDomain();
            $challengeType = $this->challengeTypeManager->getChallengeByHandle($domain->getChallengeTypeHandle());
            if ($challengeType !== null) {
                $challengeType->afterChallenge($authorizationChallenge, $logger);
            }
        } catch (Exception $foo) {
        } catch (Throwable $foo) {
        }
        $authorizationChallenge->setIsChallengePrepared(false);
        $this->em->flush($authorizationChallenge);
    }

    /**
     * @throws \Acme\Exception\Exception
     *
     * @return array
     */
    private function fetchOrderData(Order $order, LoggerInterface $logger)
    {
        $logger->debug(t('Fetching the order data'));

        return $this->fetchData($order->getCertificate()->getAccount(), $order->getOrderUrl(), $logger);
    }

    /**
     * @throws \Acme\Exception\Exception
     *
     * @return array
     */
    private function fetchAuthorizationData(AuthorizationChallenge $authorizationChallenge, LoggerInterface $logger)
    {
        $logger->debug(t('Fetching the authorization data'));

        return $this->fetchData($authorizationChallenge->getDomain()->getAccount(), $authorizationChallenge->getAuthorizationUrl(), $logger);
    }

    /**
     * @throws \Acme\Exception\Exception
     *
     * @return array
     */
    private function fetchChallengeData(AuthorizationChallenge $authorizationChallenge, LoggerInterface $logger)
    {
        $logger->debug(t('Fetching the challenge data'));

        return $this->fetchData($authorizationChallenge->getDomain()->getAccount(), $authorizationChallenge->getChallengeUrl(), $logger);
    }

    /**
     * @param string $url
     *
     * @throws \Acme\Exception\Exception
     *
     * @return array
     */
    private function fetchData(Account $account, $url, LoggerInterface $logger)
    {
        $response = $this->communicator->send(
            $account,
            $this->getFetchDataMethod($account),
            $url,
            null,
            [200, 202]
        );
        $this->logResponse($logger, $response);

        return $response->getData();
    }

    /**
     * @throws \Acme\Exception\Exception
     *
     * @return string
     */
    private function getFetchDataMethod(Account $account)
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

    private function logResponse(LoggerInterface $logger, Response $response)
    {
        $logger->debug(t('Response: %s', json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
    }
}
