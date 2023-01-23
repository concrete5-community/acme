<?php

namespace Acme\Http;

use Acme\Entity\Server;
use Concrete\Core\Application\Application;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Http\Client\Client as CoreClient;
use GuzzleHttp\Client as GuzzleHttpClient;
use Zend\Http\Client\Adapter\Curl;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Class that generates HTTP clients.
 */
final class ClientFactory
{
    /**
     * @var \Concrete\Core\Application\Application
     */
    private $app;

    /**
     * @var \Concrete\Core\Config\Repository\Repository
     */
    private $config;

    public function __construct(Application $app, Repository $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Get a new instance of an HTTP client to be used for an ACME Server.
     *
     * @return \Acme\Http\Client
     */
    public function getClientForServer(Server $server)
    {
        return $this->getClient($server->isAllowUnsafeConnections());
    }

    /**
     * Get a new instance of an HTTP client to be used for an ACME Server.
     *
     * @param bool|null $allowUnsafeConnections Set to NULL to use the default Concrete setting
     *
     * @return \Acme\Http\Client
     */
    public function getClient($allowUnsafeConnections = null)
    {
        if (is_subclass_of(CoreClient::class, GuzzleHttpClient::class)) {
            $coreClient = $this->createGuzzleClient(
                $allowUnsafeConnections === null ? [] : ['verify' => (bool) $allowUnsafeConnections]
            );
        } else {
            $coreClient = $this->createZendClient(
                $allowUnsafeConnections === null ? [] : [
                    'sslallowselfsigned' => $allowUnsafeConnections ? true : false,
                    'sslverifypeer' => $allowUnsafeConnections ? false : true,
                    'sslverifypeername' => $allowUnsafeConnections ? false : true,
                ]
            );
        }

        return new Client($coreClient);
    }

    /**
     * Get an HTTP client that acts like the ACME Server.
     *
     * @return \Acme\Http\Client
     */
    public function getAcmeServerLikeClient()
    {
        if (is_subclass_of(CoreClient::class, GuzzleHttpClient::class)) {
            $coreClient = $this->createGuzzleClient([
                'useragent' => 'Mozilla/5.0 (compatible; Fake ACME validation server; +https://github.com/concrete5-community/acme)',
                'verify' => false,
                'allow_redirects' => [
                    'max' => 10,
                    'strict' => false,
                ],
            ]);
        } else {
            $coreClient = $this->createZendClient([
                'useragent' => 'Mozilla/5.0 (compatible; Fake ACME validation server; +https://github.com/concrete5-community/acme)',
                'sslallowselfsigned' => true,
                'sslverifypeer' => false,
                'sslverifypeername' => false,
                'maxredirects' => 10,
                'strictredirects' => false,
                'keepalive' => false,
            ]);
        }

        return new Client($coreClient);
    }

    /**
     * @return \Concrete\Core\Http\Client\Client
     */
    private function createGuzzleClient(array $config)
    {
        $config += [
            'http_errors' => false,
            'useragent' => $this->getUserAgent(),
        ];
        $defaultCoreClient = $this->app->make(CoreClient::class);

        return new CoreClient($config + $defaultCoreClient->getConfig());
    }

    /**
     * @return \Concrete\Core\Http\Client\Client
     */
    private function createZendClient(array $config)
    {
        $config += [
            'useragent' => $this->getUserAgent(),
        ];
        $coreClient = $this->app->make(CoreClient::class);
        $coreClient->setOptions($config);
        if (isset($config['sslverifypeer'])) {
            $adapter = $coreClient->getAdapter();
            if ($adapter instanceof Curl) {
                $adapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, $config['sslverifypeer'] ? 2 : 0);
            }
        }

        return $coreClient;
    }

    /**
     * @return string
     */
    private function getUserAgent()
    {
        return $this->config->get('acme::http.client.useragent');
    }
}
