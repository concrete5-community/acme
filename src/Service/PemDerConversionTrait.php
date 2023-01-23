<?php

namespace Acme\Service;

defined('C5_EXECUTE') or die('Access Denied.');

trait PemDerConversionTrait
{
    /**
     * Convert a binary representation of a key (DER) to its ASCII representation (PEM).
     *
     * @param string $value the binary data
     * @param string $kind the kind of the data (eg 'CERTIFICATE')
     *
     * @return string empty string in case of problems
     */
    protected function convertDerToPem($value, $kind)
    {
        return "-----BEGIN {$kind}-----\n" . chunk_split(base64_encode($value), 64, "\n") . "-----END {$kind}-----";
    }

    /**
     * Convert an ASCII representation of a key (PEM) to its binary representation (DER).
     *
     * @param string $value
     *
     * @return string empty string in case of problems
     */
    protected function convertPemToDer($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^-.+-$/ms', $value)) {
            return '';
        }
        $value = preg_replace('/.*?^-+[^-]+-+/ms', '', $value, 1);
        $value = preg_replace('/-+[^-]+-+/', '', $value);
        $value = str_replace(["\r", "\n", ' '], '', $value);
        if (!preg_match('#^[a-zA-Z\d/+]*={0,2}$#', $value)) {
            return '';
        }
        set_error_handler(static function () {}, -1);
        try {
            $value = base64_decode($value);
        } finally {
            restore_error_handler();
        }

        return $value === false ? '' : $value;
    }
}
