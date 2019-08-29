<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme\Servers;

use Acme\Editor\ServerEditor;
use Acme\Entity\Server;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class Edit extends DashboardPageController
{
    public function view($id = '')
    {
        $server = $this->getServer($id, true);
        if ($server === null) {
            return $this->buildReturnRedirectResponse();
        }
        $this->set('server', $server);
        $this->set('otherServersExists', $this->otherServersExists($server));
        $this->set('sampleServers', $this->app->make('config')->get('acme::sample_servers'));
        $this->set('pageTitle', $server->getID() ? t('Edit ACME server') : t('Add ACME server'));
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
        $this->set('dateHelper', $this->app->make('date'));
    }

    public function submit($id = '')
    {
        if ($id === 'new') {
            $server = null;
        } else {
            $server = $this->getServer($id, false);
            if ($server === null) {
                return $this->buildReturnRedirectResponse();
            }
        }
        if (!$this->token->validate("acme-server-edit-{$id}")) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view($id);
        }
        $data = $this->request->request->all();
        $t = $this->token;
        unset($data[$t::DEFAULT_TOKEN_NAME]);
        $editor = $this->app->make(ServerEditor::class);
        if ($server === null) {
            $server = $editor->create($data, $this->error);
        } else {
            $editor->edit($server, $data, $this->error);
        }
        if ($this->error->has()) {
            return $this->view($id);
        }
        $this->flash('success', $id === 'new' ? t('The ACME server has been created') : t('The ACME server has been updated'));

        return $this->buildReturnRedirectResponse();
    }

    public function delete($id = '')
    {
        $server = $this->getServer($id, false);
        if ($server === null) {
            return $this->buildReturnRedirectResponse();
        }
        if (!$this->token->validate("acme-server-delete-{$id}")) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view($id);
        }
        $editor = $this->app->make(ServerEditor::class);
        if (!$editor->delete($server, $this->error)) {
            return $this->view($id);
        }

        $this->flash('success', t('The ACME server has been deleted.'));

        return $this->buildReturnRedirectResponse();
    }

    /***
     * @param mixed $id
     *
     * @param bool $allowNew
     * @param bool $flashOnNotFound
     *
     * @return \Acme\Entity\Server|null
     */
    private function getServer($id, $allowNew, $flashOnNotFound = true)
    {
        if ($allowNew && $id === 'new') {
            $config = $this->app->make('config');

            return Server::create()
                ->setAuthorizationPorts(preg_split('/\D+/', $config->get('acme::challenge.default_authorization_ports'), -1, PREG_SPLIT_NO_EMPTY))
            ;
        }
        $id = (int) $id;
        $server = $id === 0 ? null : $this->app->make(EntityManagerInterface::class)->find(Server::class, $id);
        if ($server !== null) {
            return $server;
        }
        if ($id !== 0 && $flashOnNotFound) {
            $this->flash('error', t('Unable to find the ACME server specified.'));
        }

        return null;
    }

    /**
     * @param \Acme\Entity\Server $server
     *
     * @return bool
     */
    private function otherServersExists(Server $server)
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $qb = $em->createQueryBuilder();

        $qb
            ->from(Server::class, 's')
            ->select('s.id')
            ->setMaxResults(1)
        ;
        if ($server->getID() !== null) {
            $qb->where($qb->expr()->neq('s.id', ':id'))->setParameter('id', $server->getID());
        }

        return $qb->getQuery()->getScalarResult() !== [];
    }

    /**
     * @return \Concrete\Core\Routing\RedirectResponse
     */
    private function buildReturnRedirectResponse()
    {
        return $this->app->make(ResponseFactoryInterface::class)->redirect(
            $this->app->make(ResolverManagerInterface::class)->resolve(['/dashboard/system/acme/servers']),
            302
        );
    }
}
