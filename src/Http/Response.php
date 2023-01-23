<?php

namespace Acme\Http;

defined('C5_EXECUTE') or die('Access Denied.');

final class Response
{
    /**
     * @var int
     */
    public $statusCode;

    /**
     * @var string
     */
    public $reasonPhrase;

    /**
     * @var string[][]
     */
    public $headers;

    /**
     * @var string
     */
    public $body;

    /**
     * @param string $name
     * @param string|mixed $onNotFound
     *
     * @return string|mixed
     */
    public function getHeader($name, $onNotFound = '')
    {
        $headers = $this->getHeaders($name);

        return $headers === [] ? $onNotFound : $headers[0];
    }

    /**
     * @param string $name
     *
     * @return string[]
     */
    public function getHeaders($name)
    {
        $nameLC = strtolower($name);
        $headersLC = array_change_key_case($this->headers, CASE_LOWER);

        return isset($headersLC[$nameLC]) ? $headersLC[$nameLC] : [];
    }
}
