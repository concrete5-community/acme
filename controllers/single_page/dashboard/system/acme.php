<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System;

use Acme\Entity\Account;
use Acme\Entity\Certificate;
use Acme\Entity\Domain;
use Acme\Entity\RemoteServer;
use Acme\Entity\Server;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Checker;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class Acme extends DashboardPageController
{
    public function view()
    {
        $resolverManager = $this->app->make(ResolverManagerInterface::class);
        $em = $this->app->make(EntityManagerInterface::class);
        $pages = [];
        $page = $this->getPageObject();
        foreach ($page->getCollectionChildren() as $childPage) {
            if ($this->canGoTo($childPage)) {
                $data = [
                    'name' => t($childPage->getCollectionName()),
                    'url' => (string) $resolverManager->resolve([$childPage]),
                    'kind' => '',
                ];
                $qb = $em->createQueryBuilder();
                switch (trim($childPage->getCollectionPath(), '/')) {
                    case 'dashboard/system/acme/servers':
                        $data['kind'] = 'servers';
                        $data['servers'] = (int) $qb->select($qb->expr()->count('x.id'))->from(Server::class, 'x')->getQuery()->getSingleScalarResult();
                        break;
                    case 'dashboard/system/acme/accounts':
                        $data['kind'] = 'accounts';
                        $data['accounts'] = (int) $qb->select($qb->expr()->count('x.id'))->from(Account::class, 'x')->getQuery()->getSingleScalarResult();
                        break;
                    case 'dashboard/system/acme/domains':
                        $data['kind'] = 'domains';
                        $data['domains'] = (int) $qb->select($qb->expr()->count('x.id'))->from(Domain::class, 'x')->getQuery()->getSingleScalarResult();
                        break;
                    case 'dashboard/system/acme/certificates':
                        $data['kind'] = 'certificates';
                        $data['certificates'] = (int) $qb->select($qb->expr()->count('x.id'))->from(Certificate::class, 'x')->getQuery()->getSingleScalarResult();
                        break;
                    case 'dashboard/system/acme/remote_servers':
                        $data['kind'] = 'remote_servers';
                        $data['remote_servers'] = (int) $qb->select($qb->expr()->count('x.id'))->from(RemoteServer::class, 'x')->getQuery()->getSingleScalarResult();
                        break;
                    case 'dashboard/system/acme/options':
                        $data['kind'] = 'options';
                        break;
                }
                $pages[] = $data;
            }
        }
        if ($pages === []) {
            return $this->app->make(ResponseFactoryInterface::class)->forbidden($this->request->getUri());
        }
        $this->set('pages', $pages);
    }

    /**
     * @param \Concrete\Core\Page\Page|null $page
     *
     * @return bool
     */
    private function canGoTo(Page $page = null)
    {
        if ($page === null || $page->isError()) {
            return false;
        }
        $checker = new Checker($page);

        return (bool) $checker->canView();
    }
}
