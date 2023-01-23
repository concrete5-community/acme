<?php

namespace Acme\Certificate;

use Acme\Entity\Account;
use Acme\Entity\Certificate;
use Acme\Entity\RevokedCertificate;
use Acme\Exception\UnrecognizedProtocolVersionException;
use Acme\Protocol\Communicator;
use Acme\Protocol\Version;
use Acme\Service\Base64EncoderTrait;
use Acme\Service\PemDerConversionTrait;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

final class Revoker
{
    use Base64EncoderTrait;

    use PemDerConversionTrait;

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
    private $communicator;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    public function __construct(Communicator $communicator, EntityManagerInterface $em)
    {
        $this->communicator = $communicator;
        $this->em = $em;
    }

    /**
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
     * @param int|null $reason the value of one of the REASON_... constants.
     * @param bool $allowFailure
     */
    public function revokeCertificateInfo(Account $account, CertificateInfo $certificateInfo, $reason = null, $allowFailure = false)
    {
        $this->revoke($certificateInfo, $account, null, $reason, $allowFailure);
    }

    /**
     * @param int|null $reason the value of one of the REASON_... constants.
     * @param bool $allowFailure
     */
    private function revoke(CertificateInfo $certificateInfo, Account $account, Certificate $certificate = null, $reason = null, $allowFailure = false)
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
     * @param int|null $reason
     *
     * @return array
     */
    private function buildPayload(CertificateInfo $certificateInfo, Account $account, $reason)
    {
        $reason = (int) $reason;
        $result = [
            'certificate' => $this->toBase64UrlSafe($this->convertPemToDer($certificateInfo->getCertificate())),
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
