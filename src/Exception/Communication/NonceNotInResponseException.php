<?php

namespace Acme\Exception\Communication;

use Zend\Http\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Exception thrown when the response from the ACME server does not contain a nonce.
 */
class NonceNotInResponseException extends Exception
{
    /**
     * The response that does not contain a nonce.
     *
     * @var \Zend\Http\Response
     */
    protected $response;

    /**
     * Create a new instance.
     *
     * @param \Zend\Http\Response $response the response that does not contain a nonce
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
     * @return \Zend\Http\Response
     */
    public function getResponse()
    {
        return $this->unknownProtocolVersion;
    }
}
