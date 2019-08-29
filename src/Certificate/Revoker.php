<?php

namespace Acme\Certificate;

use Acme\Entity\Account;
use Acme\Entity\Certificate;
use Acme\Entity\RevokedCertificate;
use Acme\Exception\UnrecognizedProtocolVersionException;
use Acme\Protocol\Communicator;
use Acme\Protocol\Version;
use Acme\Security\Crypto;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class Revoker
{
    /**
     * rfc5280 revokation reason: the private key associated with the certificate has been compromised.
     *
     * @var int
     */
    const REASON_KEY_COMPROMISE = 1;

    /**
     * rfc5280 revokation reason: the user has terminated his or her relationship with the organization indicated in the Distinguished Name attribute of the certificate.
     *
     * @var int
     */
    const REASON_AFFILIATION_CHANGED = 3;

    /**
     * rfc5280 revokation reason: a replacement certificate has been issued,.
     *
     * @var int
     */
    const REASON_SUPERSEDED = 4;

    /**
     * @var \Acme\Protocol\Communicator
     */
    protected $communicator;

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
     * @param \Acme\Security\Crypto $crypto
     * @param \Doctrine\ORM\EntityManagerInterface $em
     */
    public function __construct(Communicator $communicator, Crypto $crypto, EntityManagerInterface $em)
    {
        $this->communicator = $communicator;
        $this->crypto = $crypto;
        $this->em = $em;
    }

    /**
     * @param \Acme\Entity\Certificate $certificate
     * @param int|null $reason the value of one of the REASON_... constants.
     * @param bool $allowFailure
     */
    public function revokeCertificate(Certificate $certificate, $reason = null, $allowFailure = false)
    {
        $certificateInfo = $certificate->getCertificateInfo();
        if ($certificateInfo === null) {
            return;
        }
        $this->revoke($certificateInfo, $certificate->getAccount(), $certificate, $reason, $allowFailure);
    }

    /**
     * @param \Acme\Entity\Account $account
     * @param \Acme\Certificate\CertificateInfo $certificateInfo
     * @param int|null $reason the value of one of the REASON_... constants.
     * @param bool $allowFailure
     */
    public function revokeCertificateInfo(Account $account, CertificateInfo $certificateInfo, $reason = null, $allowFailure = false)
    {
        $this->revoke($certificateInfo, $account, null, $reason, $allowFailure);
    }

    /**
     * @param \Acme\Certificate\CertificateInfo $certificateInfo
     * @param \Acme\Entity\Account $account
     * @param \Acme\Entity\Certificate $certificate
     * @param int|null $reason the value of one of the REASON_... constants.
     * @param bool $allowFailure
     */
    protected function revoke(CertificateInfo $certificateInfo, Account $account, Certificate $certificate = null, $reason = null, $allowFailure = false)
    {
        $error = null;
        try {
            $server = $account->getServer();
            $this->communicator->send(
                $account,
                'POST',
                $server->getRevokeCertificateUrl(),
                $this->buildPayload($certificateInfo, $account, $reason),
                [200]
            );
        } catch (Exception $x) {
            $error = $x;
        } catch (Throwable $x) {
            $error = $x;
        }
        if ($error !== null && !$allowFailure) {
            throw $error;
        }
        $revokedCertificate = RevokedCertificate::create($certificateInfo)
            ->setParentCertificate($certificate)
            ->setRevocationFailureMessage($error === null ? '' : $error->getMessage())
        ;
        if ($certificate !== null) {
            $certificate->setCertificateInfo(null);
            $certificate->getRevokedCertificates()->add($revokedCertificate);
        }
        $this->em->persist($revokedCertificate);
        $this->em->flush($revokedCertificate);
        if ($certificate !== null) {
            $this->em->flush($certificate);
        }
    }

    /**
     * @param \Acme\Certificate\CertificateInfo $certificateInfo
     * @param \Acme\Entity\Account $account
     * @param int|null $reason
     *
     * @return array
     */
    protected function buildPayload(CertificateInfo $certificateInfo, Account $account, $reason)
    {
        $reason = (int) $reason;
        $result = [
            'certificate' => $this->crypto->toBase64($this->crypto->pemToDer($certificateInfo->getCertificate())),
        ];
        switch ($account->getServer()->getProtocolVersion()) {
            case Version::ACME_01:
                return $result + ['resource' => 'revoke-cert'];
            case Version::ACME_02:
                if ($reason !== 0) {
                    $result['reason'] = $reason;
                }

                return $result;
            default:
                throw UnrecognizedProtocolVersionException::create($account->getServer()->getProtocolVersion());
        }
    }
}
