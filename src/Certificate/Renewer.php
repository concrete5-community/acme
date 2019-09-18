<?php

namespace Acme\Certificate;

use Acme\Entity\Certificate;
use Acme\Entity\Order;
use Acme\Exception\CheckRevocationException;
use Acme\Exception\UnrecognizedProtocolVersionException;
use Acme\Protocol\Version;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\System\Mutex\MutexBusyException;
use Concrete\Core\System\Mutex\MutexInterface;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LogLevel;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Class to generate/renew certificates.
 */
class Renewer
{
    /**
     * Certificate state: the certificate exists, it's still valid, and we don't need to renew it.
     *
     * @var int
     */
    const CERTIFICATESTATE_GOOD = 0;

    /**
     * Certificate state: the certificate exists, it's still valid, and we don't need to renew it, but we'd need to run the certificate actions.
     *
     * @var int
     */
    const CERTIFICATESTATE_RUNACTIONS = 1;

    /**
     * Certificate state: there certificate is still valid, but it should be renewed.
     *
     * @var int
     */
    const CERTIFICATESTATE_SHOULDBERENEWED = 2;

    /**
     * Certificate state: there certificate is expired, it must be generated.
     *
     * @var int
     */
    const CERTIFICATESTATE_EXPIRED = 3;

    /**
     * Certificate state: there's no certificate, it must be generated.
     *
     * @var int
     */
    const CERTIFICATESTATE_MUSTBEGENERATED = 4;

    /**
     * @var \Concrete\Core\System\Mutex\MutexInterface
     */
    protected $mutex;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * @var \Concrete\Core\Config\Repository\Repository
     */
    protected $config;

    /**
     * @var \Acme\Certificate\OrderService
     */
    protected $orderService;

    /**
     * @var \Acme\Certificate\CertificateInfoCreator
     */
    protected $certificateInfoCreator;

    /**
     * @var \Acme\Certificate\ActionRunner
     */
    protected $actionRunner;

    /**
     * @var \Acme\Certificate\Revoker
     */
    protected $revoker;

    /**
     * @var \Acme\Certificate\RevocationChecker
     */
    protected $revocationChecker;

    /**
     * @param \Concrete\Core\System\Mutex\MutexInterface $mutex
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param \Concrete\Core\Config\Repository\Repository $config
     * @param \Acme\Certificate\OrderService $orderService
     * @param \Acme\Certificate\CertificateInfoCreator $certificateInfoCreator
     * @param \Acme\Certificate\ActionRunner $actionRunner
     * @param \Acme\Certificate\Revoker $revoker
     * @param \Acme\Certificate\RevocationChecker $revocationChecker
     */
    public function __construct(MutexInterface $mutex, EntityManagerInterface $em, Repository $config, OrderService $orderService, CertificateInfoCreator $certificateInfoCreator, ActionRunner $actionRunner, Revoker $revoker, RevocationChecker $revocationChecker)
    {
        $this->mutex = $mutex;
        $this->em = $em;
        $this->config = $config;
        $this->orderService = $orderService;
        $this->certificateInfoCreator = $certificateInfoCreator;
        $this->actionRunner = $actionRunner;
        $this->revoker = $revoker;
        $this->revocationChecker = $revocationChecker;
    }

    /**
     * Get the state of a certificate.
     *
     * @param \Acme\Entity\Certificate $certificate
     *
     * @return int The value of one of the CERTIFICATESTATE_... constants
     */
    public function getCertificateState(Certificate $certificate)
    {
        $info = $certificate->getCertificateInfo();
        if ($info === null) {
            return static::CERTIFICATESTATE_MUSTBEGENERATED;
        }
        if ($info->getEndDate() <= new DateTime()) {
            return static::CERTIFICATESTATE_EXPIRED;
        }
        $renewDaysBeforeExpiration = (int) $this->config->get('acme::renewal.daysBeforeExpiration');
        $certificateDaysSpan = (int) ceil(($info->getEndDate()->getTimestamp() - $info->getStartDate()->getTimestamp()) / (60 * 60 * 24));
        if ($renewDaysBeforeExpiration >= $certificateDaysSpan) {
            $renewDaysBeforeExpiration = $certificateDaysSpan - 1;
        }
        $expirationLimit = new DateTime("+{$renewDaysBeforeExpiration} days");
        if ($info->getEndDate() <= $expirationLimit) {
            return static::CERTIFICATESTATE_SHOULDBERENEWED;
        }
        if (!$certificate->getActions()->isEmpty() && $certificate->getActionsState() !== $certificate::ACTIONSTATEFLAG_EXECUTED) {
            return static::CERTIFICATESTATE_RUNACTIONS;
        }

        return static::CERTIFICATESTATE_GOOD;
    }

    /**
     * Perform a "next" step in the certificate generation.
     * For ACME v1 that means asking the generation of the certificate, if it fails we'll (re)authorize the domains, wait for their authorization, and re-asking th certificate.
     * For ACME v2 that means submitting an order, authorize the domains, ask the certificate generation, and download it.
     *
     * @param \Acme\Entity\Certificate $certificate
     * @param \Acme\Certificate\RenewerOptions|null $options
     *
     * @return \Acme\Certificate\RenewState
     */
    public function nextStep(Certificate $certificate, RenewerOptions $options = null)
    {
        $state = null;
        try {
            $this->mutex->execute('acme-certificaterenew-' . $certificate->getID(), function () use ($certificate, $options, &$state) {
                $state = $this->doStep($certificate, $options ?: RenewerOptions::create());
            });

            return $state;
        } catch (MutexBusyException $x) {
            return RenewState::create()
                ->chainError(t('The certificate is already being renewed elsewhere'))
            ;
        }
    }

    /**
     * Actually perform the next step, in a mutex way.
     *
     * @param \Acme\Entity\Certificate $certificate
     * @param \Acme\Certificate\RenewerOptions $options
     *
     * @return \Acme\Certificate\RenewState
     */
    protected function doStep(Certificate $certificate, RenewerOptions $options)
    {
        $state = RenewState::create();
        if ($options->isForceActionsExecution()) {
            $certificate
                ->setActionsState($certificate::ACTIONSTATE_NONE)
                ->setLastActionExecuted(null)
            ;
            $this->em->flush($certificate);
        }
        if ($certificate->getOngoingOrder() !== null) {
            $this->handleOngoingOrder($state, $certificate);
        } else {
            if ($options->isForceCertificateRenewal() || in_array($this->getCertificateState($certificate), [static::CERTIFICATESTATE_GOOD, static::CERTIFICATESTATE_RUNACTIONS], true) !== true) {
                $doRenew = true;
            } elseif ($options->isCheckRevocation()) {
                $doRenew = $this->shouldRenewForRevocation($state, $certificate);
            } else {
                $doRenew = false;
            }
            if ($doRenew) {
                $this->requestNewCertificate($state, $certificate);
            } else {
                $this->runActions($state, $certificate);
            }
        }

        return $state;
    }

    /**
     * Request a new certificate, using the 'new-cert' ACME v1 resource, or creating an ACME v2 certificate order.
     *
     * @param \Acme\Certificate\RenewState $state
     * @param \Acme\Entity\Certificate $certificate
     */
    protected function requestNewCertificate(RenewState $state, Certificate $certificate)
    {
        $protocolVersion = $certificate->getAccount()->getServer()->getProtocolVersion();
        switch ($protocolVersion) {
            case Version::ACME_01:
                $this->requestNewCertificateDirect($state, $certificate);
                break;
            case Version::ACME_02:
                $this->createNewOrder($state, $certificate);
                break;
            default:
                $state->chainCritical(UnrecognizedProtocolVersionException::create($protocolVersion)->getMessage());
                break;
        }
    }

    /**
     * Request a new certificate using the 'new-cert' resource (ACME v1 only).
     *
     * @param \Acme\Certificate\RenewState $state
     * @param \Acme\Entity\Certificate $certificate
     */
    protected function requestNewCertificateDirect(RenewState $state, Certificate $certificate)
    {
        $error = null;
        try {
            $response = $this->orderService->callAcme01NewCert($certificate);
            if ($response->getCode() === 201) {
                if (!$response->getLink('up')) {
                    return RenewState::create()
                        ->chainCritical(t('Missing the URL of the issuer certificate'))
                    ;
                }
                $issuerCertificate = $this->orderService->downloadActualCertificate($certificate->getAccount(), $response->getLink('up'), false);
                $newCertificate = $this->orderService->downloadActualCertificate($certificate->getAccount(), $response->getLocation(), true);
                $certificateInfo = $this->certificateInfoCreator->createCertificateInfo($newCertificate, $issuerCertificate, true);
                $this->setNewCertificateInfo($certificate, $certificateInfo);
                $state
                    ->chainNotice(t('The certificate has been renewed'))
                    ->setNewCertificateInfo($certificateInfo)
                    ->setNextStepAfter($certificate->getActions()->isEmpty() ? null : 0)
                ;
            } elseif ($response->getCode() === 403 && $response->getErrorIdentifier() === 'urn:acme:error:unauthorized') {
                $order = $this->orderService->createAuthorizationChallenges($certificate);
                $this->setCurrentCertificateOrder($certificate, $order);
                $this->orderService->startAuthorizationChallenges($order);
                $state
                    ->chainNotice(t('The authorization process started since the ACME server needs to authorize the domains'))
                    ->setNextStepAfter(1)
                ;
            } else {
                $error = $response->getErrorDescription();
            }
        } catch (Exception $foo) {
            $error = $foo->getMessage();
        } catch (Throwable $foo) {
            $error = $foo->getMessage();
        }

        if ($error !== null) {
            $certificate->setOngoingOrder(null);
            $state->chainCritical(t('Failed to request the certificate to the ACME server: %s', $error));
        }
    }

    /**
     * Initialize a new certificate order (ACME v2 only).
     *
     * @param \Acme\Certificate\RenewState $state
     * @param \Acme\Entity\Certificate $certificate
     */
    protected function createNewOrder(RenewState $state, Certificate $certificate)
    {
        $error = null;
        try {
            $order = $this->orderService->createOrder($certificate);
            $this->setCurrentCertificateOrder($certificate, $order);
            $this->orderService->startAuthorizationChallenges($order);
            $state
                ->chainNotice(t('The order for a new certificate has been submitted'))
                ->setNextStepAfter(1)
            ;
        } catch (Exception $foo) {
            $error = $foo->getMessage();
        } catch (Throwable $foo) {
            $error = $foo->getMessage();
        }
        if ($error !== null) {
            $certificate->setOngoingOrder(null);
            $this->em->flush($certificate);
            $state->chainCritical(t('The order for a new certificate failed: %s', $error));
        }
    }

    /**
     * Refresh the ongoing authorizations (ACME v1) or certificate order (ACME v2).
     *
     * @param \Acme\Certificate\RenewState $state
     * @param \Acme\Entity\Certificate $certificate
     */
    protected function handleOngoingOrder(RenewState $state, Certificate $certificate)
    {
        $order = $certificate->getOngoingOrder();
        if ($order->getExpiration() !== null && $order->getExpiration() < new DateTime()) {
            $this->handleOngoingOrderExpired($state, $certificate);
        } else {
            $error = null;
            try {
                $this->orderService->refresh($order);
            } catch (Exception $x) {
                $error = $x;
            } catch (Throwable $x) {
                $error = $x;
            }
            if ($error === null) {
                $this->handleOngoingOrderRefreshed($state, $certificate);
            } else {
                $state
                    ->chainCritical(t('Failed to refresh the domain authorizations state: %s', $error->getMessage()))
                    ->setOrderOrAuthorizationsRequest($order)
                    ->setNextStepAfter(10)
                ;
            }
        }
    }

    /**
     * Handle the case when the the ongoing authorizations (ACME v1) or certificate order (ACME v2) are expired.
     *
     * @param \Acme\Certificate\RenewState $state
     * @param \Acme\Entity\Certificate $certificate
     */
    protected function handleOngoingOrderExpired(RenewState $state, Certificate $certificate)
    {
        $order = $certificate->getOngoingOrder();
        $certificate->setOngoingOrder(null);
        $this->em->flush($certificate);
        $this->orderService->stopAuthorizationChallenges($order);
        $state
            ->chainError(t('The domains authorization process expired on %s', $order->getExpiration()->format('c')))
            ->setOrderOrAuthorizationsRequest($order)
            ->setNextStepAfter(1)
        ;
    }

    /**
     * Refresh the ongoing authorizations (ACME v1) or certificate order (ACME v2) after it has been refreshed.
     *
     * @param \Acme\Certificate\RenewState $state
     * @param \Acme\Entity\Certificate $certificate
     */
    protected function handleOngoingOrderRefreshed(RenewState $state, Certificate $certificate)
    {
        $order = $certificate->getOngoingOrder();
        switch ($order->getStatus()) {
            case $order::STATUS_PENDING:
                $this->handleOngoingOrderPending($state, $certificate);
                break;
            case $order::STATUS_READY:
                $this->handleOngoingOrderReady($state, $certificate);
                break;
            case $order::STATUS_PROCESSING:
                $this->handleOngoingOrderProcessing($state, $certificate);
                break;
            case $order::STATUS_VALID:
                $this->handleOngoingOrderValid($state, $certificate);
                break;
            case $order::STATUS_INVALID:
            default:
                $this->handleOngoingOrderInvalid($state, $certificate);
                break;
        }
    }

    /**
     * Handle the case when the ACME server is still authorizing the domains.
     *
     * @param \Acme\Certificate\RenewState $state
     * @param \Acme\Entity\Certificate $certificate
     */
    protected function handleOngoingOrderPending(RenewState $state, Certificate $certificate)
    {
        $state
            ->chainInfo(t('The domains authorization process is still running'))
            ->setOrderOrAuthorizationsRequest($certificate->getOngoingOrder())
            ->setNextStepAfter(1)
        ;
    }

    /**
     * Continue the processing after the domains authorizations succeeded, by asking a new certificate.
     *
     * @param \Acme\Certificate\RenewState $state
     * @param \Acme\Entity\Certificate $certificate
     */
    protected function handleOngoingOrderReady(RenewState $state, Certificate $certificate)
    {
        $order = $certificate->getOngoingOrder();
        $this->orderService->stopAuthorizationChallenges($order);
        if ($order->getType() === $order::TYPE_AUTHORIZATION) {
            $certificate->setOngoingOrder(null);
            $this->em->flush($certificate);
            $state->setOrderOrAuthorizationsRequest($order);
            $this->requestNewCertificateDirect($state, $certificate);
        } else {
            $error = null;
            try {
                $this->orderService->finalizeOrder($order);
                $state
                    ->chainInfo(t('The ACME server authorized the domains, and the request for the certificate generation has been submitted'))
                    ->setOrderOrAuthorizationsRequest($order)
                    ->setNextStepAfter(1)
                ;
            } catch (Exception $x) {
                $error = $x;
            } catch (Throwable $x) {
                $error = $x;
            }
            if ($error !== null) {
                $state
                    ->chainCritical(t('Failed to request the certificate generation: %s', $error->getMessage()))
                    ->setOrderOrAuthorizationsRequest($order)
                    ->setNextStepAfter(2)
                ;
            }
        }
    }

    /**
     * Handle the case when the ACME server has been asked to generate a certificate, but the certificate is not ready yet.
     *
     * @param \Acme\Certificate\RenewState $state
     * @param \Acme\Entity\Certificate $certificate
     */
    protected function handleOngoingOrderProcessing(RenewState $state, Certificate $certificate)
    {
        $this->orderService->stopAuthorizationChallenges($certificate->getOngoingOrder());
        $state
            ->chainInfo(t('The ACME server is going to issue the requested certificate'))
            ->setOrderOrAuthorizationsRequest($certificate->getOngoingOrder())
            ->setNextStepAfter(1)
        ;
    }

    /**
     * Handle the case when the ACME server has generate a certificate: it's ready to be downloaded.
     *
     * @param \Acme\Certificate\RenewState $state
     * @param \Acme\Entity\Certificate $certificate
     */
    protected function handleOngoingOrderValid(RenewState $state, Certificate $certificate)
    {
        $order = $certificate->getOngoingOrder();
        $this->orderService->stopAuthorizationChallenges($order);
        $error = null;
        try {
            $allCertificates = $this->orderService->downloadActualCertificate($certificate->getAccount(), $order->getCertificateUrl());
            $allCertificates = trim($allCertificates);
            $matches = null;
            if (!preg_match('/(^.*?[\r\n]+---+[ \t]*END[^\r\n\-]*---+)\s*[\r\n]+\s*(---+[ \t]*BEGIN.*)$/ms', $allCertificates, $matches)) {
                $certificate->setOngoingOrder(null);
                $this->em->flush($certificate);
                $state
                    ->chainCritical(t('Failed to detect certificate and issuer certificate'))
                    ->setOrderOrAuthorizationsRequest($order)
                ;
            } else {
                $newCertificate = trim($matches[1]);
                $issuerCertificate = trim($matches[2]);
                $certificateInfo = $this->certificateInfoCreator->createCertificateInfo($newCertificate, $issuerCertificate);
                $this->setNewCertificateInfo($certificate, $certificateInfo);
                $certificate->setOngoingOrder(null);
                $this->em->flush($certificate);
                $state
                    ->chainInfo(t('The certificate has been renewed'))
                    ->setNewCertificateInfo($certificateInfo)
                    ->setNextStepAfter($certificate->getActions()->isEmpty() ? null : 0)
                ;
            }
        } catch (Exception $x) {
            $error = $x;
        } catch (Throwable $x) {
            $error = $x;
        }
        if ($error !== null) {
            $state
                ->chainError(t('Failed to download the generated certificate: %s', $error->getMessage()))
                ->setOrderOrAuthorizationsRequest($order)
                ->setNextStepAfter(2)
            ;
        }
    }

    /**
     * Handle the case when there are problems in the authorization process.
     *
     * @param \Acme\Certificate\RenewState $state
     * @param \Acme\Entity\Certificate $certificate
     */
    protected function handleOngoingOrderInvalid(RenewState $state, Certificate $certificate)
    {
        $order = $certificate->getOngoingOrder();
        $this->orderService->stopAuthorizationChallenges($order);
        $certificate->setOngoingOrder(null);
        $this->em->flush($certificate);

        $errors = [];
        foreach ($order->getAuthorizationChallenges() as $authorizationChallenge) {
            $error = $authorizationChallenge->getChallengeErrorMessage();
            if ($error !== '') {
                $errors[] = t('Problems with domain %1$s: %2$s', $authorizationChallenge->getDomain()->getHostDisplayName(), $error);
            }
        }
        if ($errors === []) {
            $errors[] = t('The domain authorization process failed.');
        }

        $state
            ->chainError(implode("\n", $errors))
            ->setOrderOrAuthorizationsRequest($order)
        ;
    }

    /**
     * @param \Acme\Entity\Certificate $certificate
     * @param \Acme\Entity\Order $order
     */
    protected function setCurrentCertificateOrder(Certificate $certificate, Order &$order)
    {
        $this->em->persist($order);
        $this->em->flush($order);
        $this->em->detach($order);
        $order = $this->em->find(Order::class, $order->getID());
        $certificate->setOngoingOrder($order);
        $this->em->flush($certificate);
    }

    /**
     * Set the new certificate.
     *
     * @param \Acme\Entity\Certificate $certificate
     * @param \Acme\Certificate\CertificateInfo $newCertificateInfo
     */
    protected function setNewCertificateInfo(Certificate $certificate, CertificateInfo $newCertificateInfo)
    {
        $oldCertificateInfo = $certificate->getCertificateInfo();
        if ($oldCertificateInfo !== null && $oldCertificateInfo->getCertificate() !== $newCertificateInfo->getCertificate()) {
            $this->revoker->revokeCertificate($certificate, Revoker::REASON_SUPERSEDED, true);
        }
        $certificate
            ->setCertificateInfo($newCertificateInfo)
            ->setActionsState($certificate::ACTIONSTATE_NONE)
            ->setLastActionExecuted(null)
        ;
        $this->em->flush($certificate);
    }

    /**
     * Check if a certificate should be renewed because it's revoked.
     *
     * @param \Acme\Certificate\RenewState $state
     * @param \Acme\Entity\Certificate $certificate
     *
     * @return bool
     */
    protected function shouldRenewForRevocation(RenewState $state, Certificate $certificate)
    {
        $certificateInfo = $certificate->getCertificateInfo();
        if ($certificateInfo === false) {
            $state->info(t('Revocation check not performed because the certificate has not been issued yet'));

            return false;
        }
        if ($certificateInfo->getOcspResponderUrl() === '') {
            $state->info(t('Revocation check not performed because the OCSP Responder URL is missing'));

            return false;
        }
        try {
            $status = $this->revocationChecker->checkRevocation($certificateInfo);
        } catch (CheckRevocationException $x) {
            $state->error(t('Revocation check failed: %s', $x->getMessage()));

            return false;
        }
        if ($status->isRevoked() === true) {
            $state->warning(t('The certificate must be renewed since it has been revoked on %s', $status->getRevokedOn() ? $status->getRevokedOn()->format('c') : '?'));

            return true;
        }
        if ($status->isRevoked() === false) {
            $state->info(t('The certificate is not revoked'));

            return false;
        }
        $state->warning(t('The OCSP Responder did not return a revocation status'));

        return false;
    }

    /**
     * Execute the (remaining) actions on a certificate.
     *
     * @param \Acme\Certificate\RenewState $state
     * @param \Acme\Entity\Certificate $certificate
     *
     * @return \Acme\Certificate\RenewState
     */
    protected function runActions(RenewState $state, Certificate $certificate)
    {
        $state->setNewCertificateInfo($certificate->getCertificateInfo());

        if ($certificate->getActions()->isEmpty()) {
            $state->chainInfo(t('No actions are defined for this certificate'));
        } else {
            $action = $this->getNextActionToBeExecuted($certificate);
            if ($action === null) {
                $certificate->setActionsState($certificate->getActionsState() & $certificate::ACTIONSTATEFLAG_EXECUTED);
                $this->em->flush($certificate);
                $state->chainInfo(t('No action needs to be executed'));
            } else {
                $state->chainInfo(t(
                    'Executing action with ID %1$s on %2$s',
                    $action->getID(),
                    $action->getRemoteServer() === null ? t('local server') : $action->getRemoteServer()->getName()
                ));
                $logIndex = $state->getEntriesCount();
                $this->actionRunner->runAction($action, $state);
                $actionsHadProblems = $state->hasMaxLevelSince($logIndex, LogLevel::ERROR);
                $certificate->setLastActionExecuted($action);
                $actionsState = $certificate->getActionsState();
                if ($actionsState & $certificate::ACTIONSTATEFLAG_EXECUTED) {
                    $actionsState = $certificate::ACTIONSTATE_NONE;
                    $certificate->setActionsState($actionsState);
                }
                if ($this->getNextActionToBeExecuted($certificate) !== null) {
                    $certificate->setActionsState($actionsState | ($actionsHadProblems ? $certificate::ACTIONSTATEFLAG_PROBLEMS : 0));
                    $state->setNextStepAfter(0);
                } else {
                    $certificate
                        ->setActionsState($actionsState | $certificate::ACTIONSTATEFLAG_EXECUTED | ($actionsHadProblems ? $certificate::ACTIONSTATEFLAG_PROBLEMS : 0))
                        ->setLastActionExecuted(null)
                    ;
                }
                $this->em->flush($certificate);
            }
        }
    }

    /**
     * Get the next action to be executed for a certificate.
     *
     * @param \Acme\Entity\Certificate $certificate
     *
     * @return \Acme\Entity\CertificateAction|null
     */
    protected function getNextActionToBeExecuted(Certificate $certificate)
    {
        if ($certificate->getActionsState() & $certificate::ACTIONSTATEFLAG_EXECUTED) {
            return null;
        }
        $lastExecutedAction = $certificate->getLastActionExecuted();
        if ($lastExecutedAction === null) {
            return $certificate->getActions()->first() ?: null;
        }
        $allActions = $certificate->getActions()->toArray();
        $index = array_search($lastExecutedAction, $allActions, true);
        if ($index === false) {
            return $allActions[0];
        }
        if ($index === count($allActions) - 1) {
            return null;
        }

        return $allActions[$index + 1];
    }
}
