<?php

namespace Acme\Service;

use Acme\Exception\Codec\JsonEncodingException;

defined('C5_EXECUTE') or die('Access Denied.');

trait JsonEncoderTrait
{
    /**
     * Render a variable in JSON format.
     *
     * @param mixed $data
     * @param string $emptyArrayRepresentation
     *
     * @throws \Acme\Exception\Codec\JsonEncodingException when we couldn't convert $data to json
     *
     * @return string
     */
    public function toJson($data, $emptyArrayRepresentation = '{}')
    {
        if ($data === []) {
            return $emptyArrayRepresentation;
        }
        set_error_handler(static function () {}, -1);
        try {
            $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        } finally {
            restore_error_handler();
        }
        if ($json === false) {
            throw JsonEncodingException::create($data);
        }

        return $json;
    }
}
