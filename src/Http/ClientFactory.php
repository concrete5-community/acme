<?php

namespace Acme\Http;

use Acme\Entity\Server;
use Concrete\Core\Application\Application;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Http\Client\Client;
use Zend\Http\Client\Adapter\Curl;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Class that generates HTTP clients.
 */
class ClientFactory
{
    /**
     * @var \Concrete\Core\Application\Application
     */
    protected $app;

    /**
     * @var \Concrete\Core\Config\Repository\Repository
     */
    protected $config;

    /**
     * @param \Concrete\Core\Application\Application $app
     * @param \Concrete\Core\Config\Repository\Repository $config
     */
    public function __construct(Application $app, Repository $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Get a new instance of an HTTP client to be used for an ACME Server.
     *
     * @param \Acme\Entity\Server $server
     *
     * @return \Concrete\Core\Http\Client\Client
     */
    public function getClientForServer(Server $server)
    {
        return $this->getClient($server->isAllowUnsafeConnections());
    }

    /**
     * Get a new instance of an HTTP client to be used for an ACME Server.
     *
     * @param bool|null $allowUnsafeConnections Set to NULL to use the default concrete5 setting
     *
     * @return \Concrete\Core\Http\Client\Client
     */
    public function getClient($allowUnsafeConnections = null)
    {
        $httpClient = $this->app->make(Client::class);
        $options = [
            'useragent' => $this->getUserAgent(),
        ];
        if ($allowUnsafeConnections !== null) {
            $options += [
                'sslallowselfsigned' => $allowUnsafeConnections ? false : true,
                'sslverifypeer' => $allowUnsafeConnections ? false : true,
                'sslverifypeername' => $allowUnsafeConnections ? false : true,
            ];
        }
        $httpClient->setOptions($options);
        if ($allowUnsafeConnections !== null) {
            $adapter = $httpClient->getAdapter();
            if ($adapter instanceof Curl) {
                $adapter->setCurlOption(CURLOPT_SSL_VERIFYHOST, $allowUnsafeConnections ? 0 : 2);
            }
        }

        return $httpClient;
    }

    /**
     * @return string
     */
    protected function getUserAgent()
    {
        return $this->config->get('acme::http.client.useragent');
    }
}
