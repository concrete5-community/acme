<?php

namespace Acme\Exception\Communication;

use Acme\Http\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Exception thrown when the response from the ACME server does not contain a nonce.
 */
final class NonceNotInResponseException extends Exception
{
    /**
     * The response that does not contain a nonce.
     *
     * @var \Acme\Http\Response
     */
    private $response;

    /**
     * Create a new instance.
     *
     * @param \Acme\Http\Response $response the response that does not contain a nonce
     *
     * @return static
     */
    public static function create(Response $response)
    {
        $result = new static(t('The response from the ACME server does not contain a %s header', 'nonce'));
        $result->response = $response;

        return $result;
    }

    /**
     * Get the response that does not contain a nonce.
     *
     * @return \Acme\Http\Response
     */
    public function getResponse()
    {
        return $this->unknownProtocolVersion;
    }
}
