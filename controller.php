<?php

namespace Concrete\Package\Acme;

use Acme\Http\AuthorizationMiddleware;
use Acme\RouteList;
use Acme\ServiceProvider;
use Concrete\Core\Database\EntityManager\Provider\ProviderAggregateInterface;
use Concrete\Core\Database\EntityManager\Provider\StandardPackageProvider;
use Concrete\Core\Http\ServerInterface;
use Concrete\Core\Package\Package;
use Concrete\Core\Routing\RouterInterface;
use Concrete\Core\Utility\Service\Identifier;

defined('C5_EXECUTE') or die('Access Denied.');

final class Controller extends Package implements ProviderAggregateInterface
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::$appVersionRequired
     */
    protected $appVersionRequired = '8.5.0';

    protected $pkgHandle = 'acme';

    protected $pkgVersion = '5.0.1';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::$packageDependencies
     */
    protected $packageDependencies = [
        'letsencrypt' => false,
    ];

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::getPackageName()
     */
    public function getPackageName()
    {
        return t('ACME');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::getPackageDescription()
     */
    public function getPackageDescription()
    {
        return t('Create and manage HTTPS certificates for your websites');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Database\EntityManager\Provider\ProviderAggregateInterface::getEntityManagerProvider()
     */
    public function getEntityManagerProvider()
    {
        return new StandardPackageProvider($this->app, $this, [
            'src/Entity' => 'Acme\Entity',
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::install()
     */
    public function install()
    {
        $this->setupAutoloader();
        parent::install();
        $this->installContentFile('config/install.xml');
        $this->app->make('config')->package($this);
        $this->configureUniqueInstallationID();
        $this->configureHttpClientUserAgent();
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Package\Package::upgrade()
     */
    public function upgrade()
    {
        $this->installContentFile('config/install.xml');
        parent::upgrade();
        $this->configureHttpClientUserAgent();
    }

    /**
     * Method called at system startup.
     */
    public function on_start()
    {
        $this->setupAutoloader();
        $this->configureServiceProviders();
        $this->configureRoutes();
        $this->configureMiddlewares();
    }

    /**
     * Includes the composer autoloader (if this package has not been installed via composer).
     */
    public function setupAutoloader()
    {
        $path = $this->getPackagePath() . '/vendor/autoload.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    /**
     * Configure the service providers.
     */
    private function configureServiceProviders()
    {
        $this->app->make(ServiceProvider::class)->register();
    }

    /**
     * Configure the routes.
     */
    private function configureRoutes()
    {
        $router = $this->app->make(RouterInterface::class);
        $routeList = $this->app->make(RouteList::class);
        $routeList->loadRoutes($router);
    }

    /**
     * Configure the middlewares.
     */
    private function configureMiddlewares()
    {
        $this->app->make(ServerInterface::class)->addMiddleware($this->app->make(AuthorizationMiddleware::class));
    }

    /**
     * Create an unique package installation id (if not already defined).
     */
    private function configureUniqueInstallationID()
    {
        $config = $this->app->make('config');
        $key = 'acme::site.unique_installation_id';
        if (!$config->get($key)) {
            $installationID = $this->app->make(Identifier::class)->getString(64);
            $config->set($key, $installationID);
            $config->save($key, $installationID);
        }
    }

    /**
     * Configure the useragent of the HTTP client.
     */
    private function configureHttpClientUserAgent()
    {
        $config = $this->app->make('config');
        $useragent = sprintf($config->get('acme::http.client.useragent_pattern'), $this->getPackageVersion());
        $config->set('acme::http.client.useragent', $useragent);
        $config->save('acme::http.client.useragent', $useragent);
    }
}
