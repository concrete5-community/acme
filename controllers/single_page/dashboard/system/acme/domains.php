<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme;

use Acme\DomainService;
use Acme\Entity\Account;
use Acme\Entity\Domain;
use Acme\Entity\Server;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class Domains extends DashboardPageController
{
    public function view()
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $servers = $em->getRepository(Server::class)->findBy([], ['name' => 'ASC', 'id' => 'ASC']);
        $this->set('servers', $servers);
        if ($servers === []) {
            $numAccounts = 0;
        } else {
            $qb = $em->createQueryBuilder();
            $numAccounts = (int) $qb
                ->from(Account::class, 'a')
                ->select($qb->expr()->count('a.id'))
                ->getQuery()->getSingleScalarResult();
        }
        $this->set('numAccounts', $numAccounts);
        if ($numAccounts === 0) {
            $this->set('serversWithAccounts', []);
            $this->set('domains', []);
        } else {
            $serversWithAcconts = array_values(
                array_filter(
                    $servers,
                    function (Server $server) {
                        return $server->getAccounts()->count() > 0;
                    }
                )
            );
            $this->set('serversWithAccounts', $serversWithAcconts);
            $repo = $em->getRepository(Domain::class);
            $this->set('domains', $repo->findBy([], ['hostname' => 'ASC', 'id' => 'ASC']));
        }
        $this->set('domainService', $this->app->make(DomainService::class));
        $this->set('dateHelper', $this->app->make('date'));
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
    }
}
