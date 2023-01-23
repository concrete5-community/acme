<?php

namespace Acme\Http;

use Acme\Exception\RuntimeException;
use Concrete\Core\Http\Client\Client as CoreClient;
use Exception;
use GuzzleHttp\Client as GuzzleHttpClient;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Throwable;
use Zend\Http\Header\Authorization;
use Zend\Http\Request as ZendRequest;
use Zend\Http\Response as ZendResponse;

defined('C5_EXECUTE') or die('Access Denied.');

final class Client
{
    /**
     * @var \Concrete\Core\Http\Client\Client
     */
    private $coreClient;

    public function __construct(CoreClient $coreClient)
    {
        $this->coreClient = $coreClient;
    }

    /**
     * @param string $url
     * @param array $headers Supported headers (case insensitive): 'Authorization', 'Content-Type'
     *
     * @throws \Acme\Exception\RuntimeException
     *
     * @return \Acme\Http\Response
     */
    public function head($url, array $headers = [])
    {
        if ($this->coreClient instanceof GuzzleHttpClient) {
            return $this->sendGuzzleRequest('head', $url, $headers);
        }

        return $this->sendZendRequest('HEAD', $url, $headers);
    }

    /**
     * @param string $url
     * @param array $headers Supported headers (case insensitive): 'Authorization', 'Content-Type'
     *
     * @throws \Acme\Exception\RuntimeException
     *
     * @return \Acme\Http\Response
     */
    public function get($url, array $headers = [])
    {
        if ($this->coreClient instanceof GuzzleHttpClient) {
            return $this->sendGuzzleRequest('get', $url, $headers);
        }

        return $this->sendZendRequest('GET', $url, $headers);
    }

    /**
     * @param string $url
     * @param string $rawBody
     * @param array $headers Supported headers (case insensitive): 'Authorization', 'Content-Type'
     *
     * @throws \Acme\Exception\RuntimeException
     *
     * @return \Acme\Http\Response
     */
    public function post($url, $rawBody, array $headers = [])
    {
        if ($this->coreClient instanceof GuzzleHttpClient) {
            return $this->sendGuzzleRequest('post', $url, $headers, $rawBody);
        }

        return $this->sendZendRequest('POST', $url, $headers, $rawBody);
    }

    /**
     * @param string $url
     * @param array $headers Supported headers (case insensitive): 'Authorization', 'Content-Type'
     *
     * @throws \Acme\Exception\RuntimeException
     *
     * @return \Acme\Http\Response
     */
    public function delete($url, array $headers = [])
    {
        if ($this->coreClient instanceof GuzzleHttpClient) {
            return $this->sendGuzzleRequest('delete', $url, $headers);
        }

        return $this->sendZendRequest('DELETE', $url, $headers);
    }

    /**
     * @param string $method
     * @param string $url
     * @param string|null $rawBody
     *
     * @throws \Acme\Exception\RuntimeException
     *
     * @return \Acme\Http\Response
     */
    private function sendGuzzleRequest($method, $url, array $headers = [], $rawBody = null)
    {
        $options = [];
        if ($rawBody !== null) {
            $options['body'] = $rawBody;
        }
        if ($headers !== []) {
            $options['headers'] = $headers;
        }
        try {
            $response = $this->coreClient->{$method}($url, $options);
        } catch (Exception $x) {
            throw new RuntimeException($x->getMessage(), $x->getCode(), $x);
        } catch (Throwable $x) {
            throw new RuntimeException($x->getMessage(), $x->getCode(), $x);
        }

        return $this->parseGuzzleResponse($response);
    }

    /**
     * @param string $method
     * @param string $url
     * @param string|null $rawBody
     *
     * @throws \Acme\Exception\RuntimeException
     *
     * @return \Acme\Http\Response
     */
    private function sendZendRequest($method, $url, array $headers = [], $rawBody = null)
    {
        $this->coreClient->resetParameters(true);
        $this->coreClient->setMethod($method);
        $request = new ZendRequest();
        $request->setMethod($method)->setUri($url);
        if ($rawBody !== null) {
            $request->setContent($rawBody);
        }
        $headerKeys = array_keys($headers);
        $headerKeysLowerCase = array_map('strtolower', $headerKeys);
        $p = array_search('content-type', $headerKeysLowerCase, true);
        if ($p !== false) {
            $key = $headerKeys[$p];
            $this->coreClient->setEncType($headers[$key]);
        }
        $p = array_search('authorization', $headerKeysLowerCase, true);
        if ($p !== false) {
            $key = $headerKeys[$p];
            $request->getHeaders()->addHeader(new Authorization($headers[$key]));
        }
        try {
            $response = $this->coreClient->send($request);
        } catch (Exception $x) {
            throw new RuntimeException($x->getMessage(), $x->getCode(), $x);
        } catch (Throwable $x) {
            throw new RuntimeException($x->getMessage(), $x->getCode(), $x);
        }

        return $this->parseZendResponse($response);
    }

    /**
     * @return \Acme\Http\Response
     */
    private function parseGuzzleResponse(PsrResponse $response)
    {
        $result = new Response();
        $result->statusCode = $response->getStatusCode();
        $result->reasonPhrase = $response->getReasonPhrase();
        $result->headers = $response->getHeaders();
        $result->body = $response->getBody()->getContents();

        return $result;
    }

    /**
     * @return \Acme\Http\Response
     */
    private function parseZendResponse(ZendResponse $response)
    {
        $result = new Response();
        $result->statusCode = $response->getStatusCode();
        $result->reasonPhrase = $response->getReasonPhrase();
        $headers = [];
        foreach ($response->getHeaders()->toArray() as $key => $value) {
            $headers[$key] = is_array($value) ? $value : [$value];
        }
        $result->headers = $headers;
        $result->body = $response->getBody();

        return $result;
    }
}
