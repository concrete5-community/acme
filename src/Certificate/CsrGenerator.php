<?php

namespace Acme\Certificate;

use Acme\Crypto\Engine;
use Acme\Crypto\PrivateKey;
use Acme\Entity\Certificate;
use Acme\Entity\Domain;
use Acme\Exception\NotImplementedException;
use Acme\Exception\RuntimeException;
use phpseclib\File\X509 as X5092;
use phpseclib3\Crypt\RSA as RSA3;
use phpseclib3\File\X509 as X5093;

defined('C5_EXECUTE') or die('Access Denied.');

final class CsrGenerator
{
    /**
     * @var int
     */
    private $engineID;

    /**
     * @param int|null $engineID The value of one of the Acme\Crypto\Engine constants
     */
    public function __construct($engineID = null)
    {
        $this->engineID = $engineID === null ? Engine::get() : $engineID;
    }

    /**
     * Generate a Certificate Signign Request for a certificate.
     *
     * @throws \Acme\Exception\Exception
     *
     * @return string
     */
    public function generateCsrFromCertificate(Certificate $certificate)
    {
        $domains = [];
        foreach ($certificate->getDomains() as $certificateDomain) {
            $domains[] = $certificateDomain->getDomain();
        }

        return $this->generateCsrFromDomainList($certificate->getPrivateKey(), $domains);
    }

    /**
     * Generate a Certificate Signign Request for a set of domains.
     *
     * @param string $privateKeyString
     * @param \Acme\Entity\Domain[] $domains
     *
     * @throws \Acme\Exception\Exception
     *
     * @return string
     */
    private function generateCsrFromDomainList($privateKeyString, array $domains)
    {
        $privateKey = PrivateKey::fromString($privateKeyString, $this->engineID);
        $primaryDomain = array_shift($domains);
        if (!$primaryDomain instanceof Domain) {
            throw new RuntimeException(t('No domains for the CSR'));
        }
        $domain = $primaryDomain;
        $altNames = [];
        for (;;) {
            $altNames[] = ['dNSName' => $domain->getPunycodeDisplayName()];
            if ($domains === []) {
                break;
            }
            $domain = array_shift($domains);
        }
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
                $x509 = new X5092();
                $x509->setPrivateKey($privateKey->getUnderlyingObject());
                break;
            case Engine::PHPSECLIB3:
                $x509 = new X5093();
                $x509->setPrivateKey($privateKey->getUnderlyingObject()->withPadding(RSA3::SIGNATURE_PKCS1));
                break;
            default:
                throw new NotImplementedException();
        }
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                $x509->setDNProp('id-at-commonName', $primaryDomain->getPunycodeDisplayName());
                if ($x509->loadCSR($this->signAndSave($x509)) === false) {
                    throw new RuntimeException(t('Failed to generate the CSR'));
                }
                if ($altNames !== []) {
                    $x509->setExtension('id-ce-subjectAltName', $altNames);
                }
                $x509->setExtension('id-ce-keyUsage', ['encipherOnly']);

                return $this->signAndSave($x509);
            default:
                throw new NotImplementedException();
        }
    }

    /**
     * @param \phpseclib\File\X509|\phpseclib3\File\X509 $x509
     *
     * @throws \Acme\Exception\Exception
     *
     * @return string
     */
    private function signAndSave($x509)
    {
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
                $signed = $x509->signCSR('sha256WithRSAEncryption');
                break;
            case Engine::PHPSECLIB3:
                $signed = $x509->signCSR();
                break;
            default:
                throw new NotImplementedException();
        }
        if ($signed === false) {
            throw new RuntimeException(t('Failed to sign the CSR'));
        }
        $saved = $x509->saveCSR($signed);
        if ($saved === false) {
            throw new RuntimeException(t('Failed to generate the CSR'));
        }

        return $saved;
    }
}
