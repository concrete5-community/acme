<?php

namespace Acme\Protocol;

use Acme\Entity\Server;
use Acme\Exception\Communication\NonceNotInResponseException;
use Acme\Http\ClientFactory;
use Acme\Http\Response as HttpResponse;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Class that generates/checks nonces to be used with ACME server calls.
 */
final class NonceManager
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
    private $clientFactory;

    /**
     * @var array[]
     */
    private $nonces = [];

    public function __construct(ClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    /**
     * Get a nonce to be used to communicate to an ACME server.
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
     */
    public function parseResponseForNewNonce(Server $server, HttpResponse $response)
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
     * @throws \Acme\Exception\Communication\NonceNotInResponseException when the ACME Server did not provided a new nonce
     *
     * @return string
     */
    private function generateNonce(Server $server)
    {
        $newNonceUrl = $server->getNewNonceUrl();
        $httpClient = $this->clientFactory->getClientForServer($server);
        $response = $httpClient->head($newNonceUrl);
        $nonce = $this->getNonceFromResponse($response);
        if ($nonce === '') {
            throw NonceNotInResponseException::create($response);
        }

        return $nonce;
    }

    /**
     * Extract a nonce from a response.
     *
     * @return string empty string if not found
     */
    private function getNonceFromResponse(HttpResponse $response)
    {
        return $response->getHeader(self::NONCE_RESPONSE_HEADER);
    }
}
