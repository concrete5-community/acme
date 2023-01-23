<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme;

use Acme\Entity\Account;
use Acme\Entity\Server;
use Acme\Service\UI;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

final class Accounts extends DashboardPageController
{
    public function view()
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $this->set('servers', $em->getRepository(Server::class)->findBy([], ['name' => 'ASC', 'id' => 'ASC']));
        $this->set('accounts', $em->getRepository(Account::class)->findBy([], ['name' => 'ASC', 'id' => 'ASC']));
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
        $this->set('dateHelper', $this->app->make('date'));
        $this->set('ui', $this->app->make(UI::class));
    }
}
