<?php

namespace Acme\Certificate;

use Acme\Entity\Certificate;
use Acme\Entity\Domain;
use Acme\Exception\RuntimeException;
use phpseclib\Crypt\RSA;
use phpseclib\File\X509;

defined('C5_EXECUTE') or die('Access Denied.');

class CsrGenerator
{
    /**
     * Generate a Certificate Signign Request for a certificate.
     *
     * @param \Acme\Entity\Certificate $certificate
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
    public function generateCsrFromDomainList($privateKeyString, array $domains)
    {
        $privateKey = $this->loadPrivateKey($privateKeyString);
        $csr = $this->createCsr($privateKey, $domains);

        return $this->signCsr($privateKey, $csr);
    }

    /**
     * @param string $privateKeyString
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \phpseclib\Crypt\RSA
     */
    protected function loadPrivateKey($privateKeyString)
    {
        $privateKey = new RSA();
        if ($privateKey->loadKey($privateKeyString) == false || $privateKey->getPrivateKey() === false || $privateKey->getPublicKey() === false) {
            throw new RuntimeException(t('The specified private key is not valid.'));
        }

        return $privateKey;
    }

    /**
     * @param \phpseclib\Crypt\RSA $privateKey
     * @param \Acme\Entity\Domain[] $domains
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \phpseclib\File\X509
     */
    protected function createCsr(RSA $privateKey, array $domains)
    {
        $domain = array_shift($domains);
        if (!$domain instanceof Domain) {
            throw new RuntimeException(t('No domains for the CSR'));
        }

        $csr = new X509();
        $csr->setPrivateKey($privateKey);
        $csr->setDNProp('id-at-commonName', $domain->getPunycodeDisplayName());

        $signed = $csr->signCSR('sha256WithRSAEncryption');
        $saved = ($signed === false) ? false : $csr->saveCSR($signed);
        $loaded = ($saved === false) ? false : $csr->loadCSR($saved);
        if (!$loaded) {
            throw new RuntimeException(t('Failed to generate the CSR'));
        }

        $altNames = [];
        for (;;) {
            $altNames[] = ['dNSName' => $domain->getPunycodeDisplayName()];
            if (empty($domains)) {
                break;
            }
            $domain = array_shift($domains);
        }
        /*
        while (!empty($domains)) {
            $altNames[] = ['dNSName' => array_shift($domains)->getPunycodeDisplayName()];
        }
         */
        if ($altNames !== []) {
            $csr->setExtension('id-ce-subjectAltName', $altNames);
        }
        $csr->setExtension('id-ce-keyUsage', ['encipherOnly']);

        return $csr;
    }

    /**
     * @param \phpseclib\Crypt\RSA $privateKey
     * @param \phpseclib\File\X509 $csr
     *
     * @throws \Acme\Exception\Exception
     *
     * @return string
     */
    protected function signCsr(RSA $privateKey, X509 $csr)
    {
        $csr->setPrivateKey($privateKey);
        $result = $csr->saveCSR($csr->signCSR('sha256WithRSAEncryption'), X509::FORMAT_PEM);
        if ($result === false) {
            throw new RuntimeException(t('Failed to sign the CSR'));
        }

        return $result;
    }
}
