<?php

namespace Acme\Service;

use Acme\Exception\Codec\Base64EncodingException;

defined('C5_EXECUTE') or die('Access Denied.');

trait Base64EncoderTrait
{
    /**
     * Render a variable to base 64 encoding with URL and filename safe alphabet.
     *
     * @param string $str
     *
     * @throws \Acme\Exception\Codec\Base64EncodingException when we couldn't convert $data to base-64
     *
     * @return string
     *
     * @see https://tools.ietf.org/html/rfc4648#section-5
     */
    protected function toBase64UrlSafe($str)
    {
        if (!is_string($str)) {
            throw Base64EncodingException::create($str);
        }
        set_error_handler(static function () {}, -1);
        try {
            $base64 = base64_encode($str);
        } finally {
            restore_error_handler();
        }
        if ($base64 === false) {
            throw Base64EncodingException::create($str);
        }

        return rtrim(strtr($base64, '+/', '-_'), '=');
    }
}
