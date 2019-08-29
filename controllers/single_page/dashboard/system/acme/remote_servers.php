<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme;

use Acme\Entity\RemoteServer;
use Acme\Filesystem\DriverManager;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class RemoteServers extends DashboardPageController
{
    public function view()
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $repo = $em->getRepository(RemoteServer::class);
        $this->set('remoteServers', $repo->findBy([], ['name' => 'ASC', 'id' => 'ASC']));
        $this->set('filesystemDriverManager', $this->app->make(DriverManager::class));
        $this->set('dateHelper', $this->app->make('date'));
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
    }
}
