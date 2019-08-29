<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme\Accounts;

use Acme\Editor\AccountEditor;
use Acme\Entity\Account;
use Acme\Entity\Server;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Concrete\Core\User\User;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class Add extends DashboardPageController
{
    public function view($serverID = '')
    {
        $server = $this->getServer($serverID);
        if ($server === null) {
            return $this->buildReturnRedirectResponse();
        }
        $this->set('pageTitle', t('Add new ACME account for %s', $server->getName()));
        $config = $this->app->make('config');
        $this->set('server', $server);
        $this->set('termsOfServiceUrl', $server->getTermsOfServiceUrl());
        $this->set('defaultKeySize', (int) $config->get('acme::security.key_size.default'));
        $this->set('minimumKeySize', (int) $config->get('acme::security.key_size.min'));
        $repo = $this->app->make(EntityManagerInterface::class)->getRepository(Account::class);
        $this->set('otherAccountsExist', $repo->findOneBy([]) !== null);
        $currentUser = $this->app->make(User::class);
        $this->set('currentUser', $currentUser);
        $this->set('currentUserInfo', $currentUser->getUserInfoObject());
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
    }

    public function submit($serverID = '')
    {
        $server = $this->getServer($serverID);
        if ($server === null) {
            return $this->buildReturnRedirectResponse();
        }
        if (!$this->token->validate("acme-account-add-{$serverID}")) {
            $this->error->add($this->token->getErrorMessage());
        }
        $data = $this->request->request->all();
        $t = $this->token;
        unset($data[$t::DEFAULT_TOKEN_NAME]);
        $editor = $this->app->make(AccountEditor::class);
        $account = $editor->create($server, $data, $this->error);
        if ($account === null) {
            return $this->view($server->getID());
        }

        $this->flash('success', t('The account %s has been created', $account->getName()));

        return $this->buildReturnRedirectResponse();
    }

    /***
     * @param mixed $id
     *
     * @param bool $flashOnNotFound
     *
     * @return \Acme\Entity\Server|null
     */
    private function getServer($id, $flashOnNotFound = true)
    {
        $id = (int) $id;
        $server = $id === 0 ? null : $this->app->make(EntityManagerInterface::class)->find(Server::class, $id);
        if ($server !== null) {
            return $server;
        }
        if ($id !== 0 && $flashOnNotFound) {
            $this->flash('error', t('Unable to find the requester ACME server'));
        }

        return null;
    }

    /**
     * @return \Concrete\Core\Routing\RedirectResponse
     */
    private function buildReturnRedirectResponse()
    {
        return $this->app->make(ResponseFactoryInterface::class)->redirect(
            $this->app->make(ResolverManagerInterface::class)->resolve(['/dashboard/system/acme/accounts']),
            302
        );
    }
}
