<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme;

use Acme\Entity\Server;
use Acme\Protocol\Version;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class Servers extends DashboardPageController
{
    public function view()
    {
        $repo = $this->app->make(EntityManagerInterface::class)->getRepository(Server::class);
        $this->set('servers', $repo->findBy([], ['name' => 'ASC', 'id' => 'ASC']));
        $this->set('protocolVersion', $this->app->make(Version::class));
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
        $this->set('dateHelper', $this->app->make('date'));
    }
}
