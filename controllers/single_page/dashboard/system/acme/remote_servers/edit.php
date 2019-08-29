<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme\RemoteServers;

use Acme\Editor\RemoteServerEditor;
use Acme\Entity\RemoteServer;
use Acme\Filesystem\DriverManager;
use Acme\Filesystem\RemoteDriverInterface;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class Edit extends DashboardPageController
{
    public function view($id = '')
    {
        $remoteServer = $this->getRemoteServer($id, true);
        if ($remoteServer === null) {
            return $this->buildReturnRedirectResponse();
        }
        $this->set('remoteServer', $remoteServer);
        $this->set('availableDrivers', $this->app->make(DriverManager::class)->getDrivers(true, RemoteDriverInterface::class));
        $this->set('dateHelper', $this->app->make('date'));
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
        $this->set('pageTitle', $remoteServer->getID() ? t('Edit remote server') : t('Add remote server'));
    }

    public function submit($id = '')
    {
        if ($id === 'new') {
            $remoteServer = null;
        } else {
            $remoteServer = $this->getRemoteServer($id, false);
            if ($remoteServer === null) {
                return $this->buildReturnRedirectResponse();
            }
        }
        if (!$this->token->validate("acme-remoteserver-edit-{$id}")) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view($id);
        }

        $data = $this->request->request->all();
        $t = $this->token;
        unset($data[$t::DEFAULT_TOKEN_NAME]);
        if ($id !== 'new') {
            if (empty($data['change-password'])) {
                $data['password'] = $remoteServer->getPassword();
            }
            unset($data['change-password']);
            if (empty($data['change-privateKey'])) {
                $data['privateKey'] = $remoteServer->getPrivateKey();
            }
            unset($data['change-password']);
        }

        $editor = $this->app->make(RemoteServerEditor::class);
        if ($remoteServer === null) {
            $remoteServer = $editor->create($data, $this->error);
        } else {
            $editor->edit($remoteServer, $data, $this->error);
        }
        if ($this->error->has()) {
            return $this->view($id);
        }
        $this->flash('success', $id === 'new' ? t('The remote server has been created') : t('The remote server has been updated'));

        return $this->buildReturnRedirectResponse();
    }

    public function delete($id = '')
    {
        $remoteServer = $this->getRemoteServer($id, false);
        if ($remoteServer === null) {
            return $this->buildReturnRedirectResponse();
        }
        if (!$this->token->validate("acme-remoteserver-delete-{$id}")) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view($id);
        }
        $editor = $this->app->make(RemoteServerEditor::class);
        if (!$editor->delete($remoteServer, $this->error)) {
            return $this->view($id);
        }

        $this->flash('success', t('The remote server has been deleted.'));

        return $this->buildReturnRedirectResponse();
    }

    /***
     * @param mixed $id
     *
     * @param bool $allowNew
     * @param bool $flashOnNotFound
     *
     * @return \Acme\Entity\RemoteServer|\Concrete\Core\Routing\RedirectResponse
     */
    private function getRemoteServer($id, $allowNew, $flashOnNotFound = true)
    {
        if ($allowNew && $id === 'new') {
            return RemoteServer::create();
        }
        $id = (int) $id;
        $remoteServer = $id === 0 ? null : $this->app->make(EntityManagerInterface::class)->find(RemoteServer::class, $id);
        if ($remoteServer !== null) {
            return $remoteServer;
        }
        if ($id !== 0 && $flashOnNotFound) {
            $this->flash('error', t('Unable to find the remote server specified.'));
        }

        return null;
    }

    /**
     * @return \Concrete\Core\Routing\RedirectResponse
     */
    private function buildReturnRedirectResponse()
    {
        return $this->app->make(ResponseFactoryInterface::class)->redirect(
            $this->app->make(ResolverManagerInterface::class)->resolve(['/dashboard/system/acme/remote_servers']),
            302
        );
    }
}
