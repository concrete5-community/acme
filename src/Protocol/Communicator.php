<?php

namespace Acme\Protocol;

use Acme\Entity\Account;
use Acme\Exception\RuntimeException;
use Acme\Exception\UnrecognizedProtocolVersionException;
use Acme\Http\ClientFactory;
use Acme\Http\Response as HttpResponse;
use Acme\Service\DateTimeParser;
use DateTime;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class to perform calls to communicate with ACME servers.
 */
final class Communicator
{
    /**
     * @var \Acme\Protocol\RequestBuilder
     */
    private $requestBuilder;

    /**
     * @var \Acme\Http\ClientFactory
     */
    private $clientFactory;

    /**
     * @var \Acme\Protocol\NonceManager
     */
    private $nonceManager;

    /**
     * @var \Acme\Service\DateTimeParser
     */
    private $dateTimeParser;

    public function __construct(RequestBuilder $requestBuilder, ClientFactory $clientFactory, NonceManager $nonceManager, DateTimeParser $dateTimeParser)
    {
        $this->requestBuilder = $requestBuilder;
        $this->clientFactory = $clientFactory;
        $this->nonceManager = $nonceManager;
        $this->dateTimeParser = $dateTimeParser;
    }

    /**
     * Send a request to the ACME server for a specific ACME account.
     *
     * @param string $method the HTTP method
     * @param string $url the URL to be called
     * @param array|null $payload the (optional) data to be sent to the server
     * @param int[] $acceptedResponseCodes the accected HTTP response codes
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Protocol\Response
     */
    public function send(Account $account, $method, $url, array $payload = null, array $acceptedResponseCodes = [])
    {
        $count = 0;
        $maxBadNonces = 5;
        do {
            $count++;
            $rawResponse = $this->sendRequest($account, $method, $url, $payload);
            $this->nonceManager->parseResponseForNewNonce($account->getServer(), $rawResponse);
            $response = $this->decodeResponse($rawResponse);
        } while ($count < $maxBadNonces && $this->isBadNonceResponse($response));
        $this->checkAcceptedResponseCodes($response, $acceptedResponseCodes);

        return $response;
    }

    /**
     * Create the HTTP client and prepare its request.
     *
     * @param string $method the HTTP method
     * @param string $url the URL to be called
     * @param array|null $payload the (optional) data to be sent to the server
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Http\Response
     */
    private function sendRequest(Account $account, $method, $url, array $payload = null)
    {
        $server = $account->getServer();
        $httpClient = $this->clientFactory->getClientForServer($server);
        $method = strtoupper($method);
        switch ($method) {
            case 'HEAD':
                return $httpClient->head($url);
            case 'GET':
                return $httpClient->get($url);
            case 'POST':
                $rawBody = $this->requestBuilder->buildBody($account, $url, $payload);
                switch ($server->getProtocolVersion()) {
                    case Version::ACME_01:
                        $headers = ['Content-Type' => 'application/json'];
                        break;
                    case Version::ACME_02:
                        $headers = ['Content-Type' => 'application/jose+json'];
                        break;
                    default:
                        throw UnrecognizedProtocolVersionException::create($server->getProtocolVersion());
                }

                return $httpClient->post($url, $rawBody, $headers);
            default:
                throw new RuntimeException('Not implemented');
        }
    }

    /**
     * Decode the contents of a response from an ACME server.
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Protocol\Response
     */
    private function decodeResponse(HttpResponse $response)
    {
        $result = Response::create($response->statusCode, $this->detectResponseType($response));
        switch ($result->getType()) {
            case Response::TYPE_JSON:
            case Response::TYPE_JSONERROR:
                $data = @json_decode($response->body, true);
                if ($data === null) {
                    throw new RuntimeException(t('Failed to parse the response from the server'));
                }
                $result->setData($data);
                break;
            default:
                $result->setData($response->body);
                break;
        }
        $this->analyzeResponseHeaders($response, $result);

        return $result;
    }

    /**
     * Detect the type of a response from an ACME server.
     *
     * @param \Acme\Http\Response $response the response from an ACME server
     *
     * @return int one of the \Acme\Protocol\Response::TYPE_... constants
     */
    private function detectResponseType(HttpResponse $response)
    {
        $contentType = $response->getHeader('Content-Type');
        switch (trim(preg_replace('/^([^;]+).*/', '\\1', $contentType))) {
            case 'application/json':
                return Response::TYPE_JSON;
            case 'application/problem+json':
                return Response::TYPE_JSONERROR;
            default:
                return Response::TYPE_OTHER;
        }
    }

    /**
     * Check if a response is a "bad nonce" response.
     *
     * @return bool
     */
    private function isBadNonceResponse(Response $response)
    {
        if ($response->getType() !== Response::TYPE_JSONERROR) {
            return false;
        }
        $data = $response->getData();
        if (!is_array($data) || !isset($data['type'])) {
            return false;
        }

        return in_array(
            $data['type'],
            [
                'urn:acme:badNonce',
                'urn:ietf:params:acme:error:badNonce',
            ],
            true
        );
    }

    /**
     * @param int[] $acceptedResponseCodes
     *
     * @throws \Acme\Exception\Exception
     */
    private function checkAcceptedResponseCodes(Response $response, array $acceptedResponseCodes)
    {
        if ($acceptedResponseCodes === []) {
            return;
        }
        $acceptedResponseCodes = array_map('intval', $acceptedResponseCodes);
        if (in_array($response->getCode(), $acceptedResponseCodes, true)) {
            return;
        }
        if ($response->getType() === Response::TYPE_JSONERROR) {
            throw new RuntimeException($response->getErrorDescription());
        }
        throw new RuntimeException(t('The ACME Server responded with a %s error code', $response->getCode()), max(1, $response->getCode()));
    }

    private function analyzeResponseHeaders(HttpResponse $response, Response $result)
    {
        $location = $response->getHeader('Location');
        if ($location !== '') {
            $result->setLocation($location);
        }
        $retryAfter = $response->getHeader('Retry-After');
        if ($retryAfter !== '') {
            if (is_numeric($retryAfter)) {
                $result->setRetryAfter(new DateTime("+{$retryAfter} seconds"));
            } else {
                $result->setRetryAfter($this->dateTimeParser->toDateTime($retryAfter));
            }
        }
        $m = null;
        foreach ($response->getHeaders('Link') as $link) {
            $rel = '';
            if (preg_match('/^<(.+)>(?:\s*;\s*rel\s*=\s*"?(.*?)"?)?$/i', $link, $m)) {
                if (isset($m[2])) {
                    $rel = $m[2];
                }
                $link = $m[1];
            }
            if ($result->getLink($rel) === '') {
                $result->addLinks($rel, $link);
            }
        }
    }
}
