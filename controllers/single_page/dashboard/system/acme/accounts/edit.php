<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme\Accounts;

use Acme\Crypto\FileDownloader;
use Acme\DomainService;
use Acme\Editor\AccountEditor;
use Acme\Entity\Account;
use Acme\Exception\FileDownloaderException;
use Acme\Service\UI;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

final class Edit extends DashboardPageController
{
    public function view($id = '')
    {
        $account = $this->getAccount($id);
        if ($account === null) {
            return $this->buildReturnRedirectResponse();
        }
        $this->set('account', $account);
        $this->set('otherAccountsExist', $this->otherAccountsExists($account));
        $this->set('domainService', $this->app->make(DomainService::class));
        $this->set('dateHelper', $this->app->make('date'));
        $this->set('ui', $this->app->make(UI::class));
    }

    public function submit($id = '')
    {
        $account = $this->getAccount($id);
        if ($account === null) {
            return $this->buildReturnRedirectResponse();
        }
        if (!$this->token->validate('acme-account-edit-' . $account->getID())) {
            $this->error->add($this->token->getErrorMessage());
        }

        $data = $this->request->request->all();
        $t = $this->token;
        unset($data[$t::DEFAULT_TOKEN_NAME]);

        $editor = $this->app->make(AccountEditor::class);
        if (!$editor->edit($account, $data, $this->error)) {
            return $this->view($account->getID());
        }

        $this->flash('success', t('The account has been updated'));

        return $this->buildReturnRedirectResponse();
    }

    public function download_key($id = '')
    {
        $account = $this->getAccount($id);
        if ($account === null) {
            return $this->buildReturnRedirectResponse();
        }
        if (!$this->token->validate('acme-account-download_key-' . $account->getID())) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view($id);
        }
        $downloader = $this->app->make(FileDownloader::class);
        try {
            return $downloader->download(
                $this->request->request->get('what'),
                $this->request->request->get('format'),
                [
                    FileDownloader::WHAT_PRIVATEKEY => $account->getPrivateKey(),
                ],
                t('Key for account %1$s at server %2$s', $account->getName(), $account->getServer()->getName())
            );
        } catch (FileDownloaderException $x) {
            $this->error->add($x->getMessage());

            return $this->view($id);
        }
    }

    public function delete($id = '')
    {
        $account = $this->getAccount($id);
        if ($account === null) {
            return $this->buildReturnRedirectResponse();
        }
        if (!$this->token->validate('acme-account-delete-' . $account->getID())) {
            throw new UserMessageException($this->token->getErrorMessage());
        }

        $editor = $this->app->make(AccountEditor::class);
        if (!$editor->delete($account, $this->error)) {
            return $this->view($id);
        }

        $this->flash('success', t('The account has been removed'));

        return $this->buildReturnRedirectResponse();
    }

    /**
     * @param int|string $id
     * @param bool $flashOnNotFound
     *
     * @return \Acme\Entity\Account|null
     */
    private function getAccount($id, $flashOnNotFound = true)
    {
        $id = (int) $id;
        $account = $id === 0 ? null : $this->app->make(EntityManagerInterface::class)->find(Account::class, $id);
        if ($account !== null) {
            return $account;
        }
        if ($id !== 0 && $flashOnNotFound) {
            $this->flash('error', t('Unable to find the account specified.'));
        }

        return null;
    }

    /**
     * @return bool
     */
    private function otherAccountsExists(Account $account)
    {
        $em = $this->app->make(EntityManagerInterface::class);
        $qb = $em->createQueryBuilder();

        return $qb
            ->from(Account::class, 'a')
            ->select('a.id')
            ->where($qb->expr()->neq('a.id', ':id'))->setParameter('id', $account->getID())
            ->getQuery()->getScalarResult() !== []
        ;
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
