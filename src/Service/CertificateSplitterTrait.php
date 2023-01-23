<?php

namespace Acme\Service;

defined('C5_EXECUTE') or die('Access Denied.');

trait CertificateSplitterTrait
{
    /**
     * @param string $certificate
     *
     * @return string[]
     */
    protected function splitCertificates($certificate)
    {
        $normalizedCertificate = str_replace("\r", "\n", str_replace("\r\n", "\n", $certificate));
        $normalizedCertificate = preg_replace("/[ \t]+/", ' ', $normalizedCertificate);
        $normalizedCertificate = trim(preg_replace('/\s*\n\s*/', "\n", $normalizedCertificate)) . "\n";
        $matches = null;
        if (!preg_match_all('/(?<certificates>---+ ?BEGIN [^\n]+---+\n.+?\n---+ ?END [^\n]+---+\n)/s', $normalizedCertificate, $matches)) {
            return [$certificate];
        }
        $certificates = array_map('trim', $matches['certificates']);
        if ($normalizedCertificate !== implode("\n", $certificates) . "\n") {
            return [$certificate];
        }

        return $certificates;
    }
}
