<?php

namespace Acme\Protocol;

use Acme\Entity\Server;
use Acme\Exception\Communication\NonceNotInResponseException;
use Acme\Http\ClientFactory;
use Zend\Http\Header\HeaderInterface;
use Zend\Http\Response;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Class that generates/checks nonces to be used with ACME server calls.
 */
class NonceManager
{
    /**
     * The name of the response header that contains nonces.
     *
     * @var string
     */
    const NONCE_RESPONSE_HEADER = 'Replay-Nonce';

    /**
     * @var \Acme\Http\ClientFactory
     */
    protected $clientFactory;

    /**
     * @var array[]
     */
    protected $nonces = [];

    /**
     * @param \Acme\Http\ClientFactory $clientFactory
     */
    public function __construct(ClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * Get a nonce to be used to communicate to an ACME server.
     *
     * @param \Acme\Entity\Server $server
     *
     * @throws \Acme\Exception\Communication\NonceNotInResponseException when the ACME Server did not provided a new nonce
     *
     * @return string
     */
    public function getNonceForRequest(Server $server)
    {
        $newNonceUrl = $server->getNewNonceUrl();
        if (isset($this->nonces[$newNonceUrl])) {
            $nonce = $this->nonces[$newNonceUrl];
            unset($this->nonces[$newNonceUrl]);

            return $nonce;
        }

        return $this->generateNonce($server);
    }

    /**
     * Parse a response from an ACME server and extract a new nonce from it (it present).
     *
     * @param \Acme\Entity\Server $server
     * @param \Zend\Http\Response $response
     */
    public function parseResponseForNewNonce(Server $server, Response $response)
    {
        $nonce = $this->getNonceFromResponse($response);
        if ($nonce === '') {
            return;
        }
        $newNonceUrl = $server->getNewNonceUrl();
        $this->nonces[$newNonceUrl] = $nonce;
    }

    /**
     * Generate a new nonce.
     *
     * @param \Acme\Entity\Server $server
     *
     * @throws \Acme\Exception\Communication\NonceNotInResponseException when the ACME Server did not provided a new nonce
     *
     * @return string
     */
    protected function generateNonce(Server $server)
    {
        $newNonceUrl = $server->getNewNonceUrl();
        $httpClient = $this->clientFactory->getClientForServer($server);
        $httpClient->getRequest()->setMethod('HEAD')->setUri($newNonceUrl);
        $response = $httpClient->send();
        $nonce = $this->getNonceFromResponse($response);
        if ($nonce === '') {
            throw NonceNotInResponseException::create($response);
        }

        return $nonce;
    }

    /**
     * Extract a nonce from a response.
     *
     * @param \Zend\Http\Response $response
     *
     * @return string empty string if not found
     */
    protected function getNonceFromResponse(Response $response)
    {
        $header = $response->getHeaders()->get(static::NONCE_RESPONSE_HEADER);

        return $header instanceof HeaderInterface ? $header->getFieldValue() : '';
    }
}
