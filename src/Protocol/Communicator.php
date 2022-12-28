<?php

namespace Acme\Protocol;

use Acme\Entity\Account;
use Acme\Exception\RuntimeException;
use Acme\Exception\UnrecognizedProtocolVersionException;
use Acme\Http\ClientFactory;
use Acme\Service\DateTimeParser;
use DateTime;
use Exception;
use Throwable;
use Zend\Http\Header\HeaderInterface;
use Zend\Http\Header\Location;
use Zend\Http\Header\RetryAfter;
use Zend\Http\Response as ZendResponse;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class to perform calls to communicate with ACME servers.
 */
class Communicator
{
    /**
     * @var \Acme\Protocol\RequestBuilder
     */
    protected $requestBuilder;

    /**
     * @var \Acme\Http\ClientFactory
     */
    protected $clientFactory;

    /**
     * @var \Acme\Protocol\NonceManager
     */
    protected $nonceManager;

    /**
     * @var \Acme\Service\DateTimeParser
     */
    protected $dateTimeParser;

    /**
     * @param \Acme\Protocol\RequestBuilder $requestBuilder
     * @param \Acme\Http\ClientFactory $clientFactory
     * @param \Acme\Protocol\NonceManager $nonceManager
     * @param \Acme\Service\DateTimeParser $dateTimeParser
     */
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
     * @param \Acme\Entity\Account $account
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
            $request = $this->createRequest($account, $method, $url, $payload);
            $rawResponse = $request->send();

            $this->nonceManager->parseResponseForNewNonce($account->getServer(), $rawResponse);

            $response = $this->decodeResponse($rawResponse);
        } while ($count < $maxBadNonces && $this->isBadNonceResponse($response));

        $this->checkAcceptedResponseCodes($response, $acceptedResponseCodes);

        return $response;
    }

    /**
     * Create the HTTP client and prepare its request.
     *
     * @param \Acme\Entity\Account $account
     * @param string $method the HTTP method
     * @param string $url the URL to be called
     * @param array|null $payload the (optional) data to be sent to the server
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Concrete\Core\Http\Client\Client
     */
    protected function createRequest(Account $account, $method, $url, array $payload = null)
    {
        $server = $account->getServer();
        $httpClient = $this->clientFactory->getClientForServer($server);
        $httpClient
            ->setMethod($method)
            ->setUri($url)
        ;
        if (!in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
            $httpClient->setRawBody($this->requestBuilder->buildBody($account, $url, $payload));
            switch ($server->getProtocolVersion()) {
                case Version::ACME_01:
                    $httpClient->setEncType('application/json');
                    break;
                case Version::ACME_02:
                    $httpClient->setEncType('application/jose+json');
                    break;
                default:
                    throw UnrecognizedProtocolVersionException::create($server->getProtocolVersion());
            }
        }

        return $httpClient;
    }

    /**
     * Decode the contents of a response from an ACME server.
     *
     * @param \Zend\Http\Response $response
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Protocol\Response
     */
    protected function decodeResponse(ZendResponse $response)
    {
        $result = Response::create(
            $response->getStatusCode(),
            $this->detectResponseType($response)
        );
        switch ($result->getType()) {
            case Response::TYPE_JSON:
            case Response::TYPE_JSONERROR:
                $data = @json_decode($response->getBody(), true);
                if ($data === null) {
                    throw new RuntimeException(t('Failed to parse the response from the server'));
                }
                $result->setData($data);
                break;
            default:
                $result->setData($response->getBody());
                break;
        }
        $this->analyzeResponseHeaders($response, $result);

        return $result;
    }

    /**
     * Detect the type of a response from an ACME server.
     *
     * @param \Zend\Http\Response $response the response from an ACME server
     *
     * @return int one of the \Acme\Protocol\Response::TYPE_... constants
     */
    protected function detectResponseType(ZendResponse $response)
    {
        $contentTypeHeader = $response->getHeaders()->get('Content-Type');
        if (!$contentTypeHeader instanceof HeaderInterface) {
            return Response::TYPE_OTHER;
        }
        switch (trim(preg_replace('/^([^;]+).*/', '\\1', $contentTypeHeader->getFieldValue()))) {
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
     * @param \Acme\Protocol\Response $response
     *
     * @return bool
     */
    protected function isBadNonceResponse(Response $response)
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
     * @param \Acme\Protocol\Response $response
     * @param int[] $acceptedResponseCodes
     *
     * @throws \Acme\Exception\Exception
     */
    protected function checkAcceptedResponseCodes(Response $response, array $acceptedResponseCodes)
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

    /**
     * @param \Zend\Http\Response $response
     * @param \Acme\Protocol\Response $result
     */
    protected function analyzeResponseHeaders(ZendResponse $response, Response $result)
    {
        $m = null;
        foreach ($response->getHeaders() as $header) {
            if ($header instanceof HeaderInterface) {
                if ($header instanceof Location) {
                    $result->setLocation($header->getFieldValue());
                } elseif ($header instanceof RetryAfter) {
                    try {
                        $value = $header->getFieldValue();
                        if (is_int($value)) {
                            $result->setRetryAfter(new DateTime('+300 seconds'));
                        } else {
                            $result->setRetryAfter($this->dateTimeParser->toDateTime($value));
                        }
                    } catch (Exception $x) {
                    } catch (Throwable $x) {
                    }
                } else {
                    switch (strtolower($header->getFieldName())) {
                        case 'link':
                            $link = trim($header->getFieldValue());
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
                            break;
                    }
                }
            }
        }
    }
}
