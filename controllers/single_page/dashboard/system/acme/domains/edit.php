<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme\Domains;

use Acme\ChallengeType\ChallengeTypeManager;
use Acme\DomainService;
use Acme\Editor\DomainEditor;
use Acme\Entity\Account;
use Acme\Entity\Domain;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Filesystem\ElementManager;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use MLocati\IDNA\DomainName;
use MLocati\IDNA\Exception\Exception as IDNAException;

defined('C5_EXECUTE') or die('Access Denied.');

class Edit extends DashboardPageController
{
    public function view($domainID = '', $accountID = '')
    {
        $domain = $this->getDomain($domainID, $accountID);
        if ($domain === null) {
            return $this->buildReturnRedirectResponse();
        }
        $this->set('domain', $domain);
        if ($domain->getID() === null) {
            $hostname = (string) $this->request->getHost();
            if (!filter_var($hostname, FILTER_VALIDATE_IP)) {
                try {
                    $domain->setHostname(DomainName::fromName($hostname)->getName());
                } catch (IDNAException $foo) {
                }
            }
        }
        $deviation = null;
        if ($domain->getHostname() !== '') {
            $domainName = DomainName::fromName($domain->getHostname());
            if ($domainName->isDeviated()) {
                $deviation = [
                    'name' => $domainName->getDeviatedName(),
                    'punycode' => $domainName->getDeviatedPunycode(),
                ];
            }
        }
        $this->set('deviation', $deviation);
        $this->set('domainService', $this->app->make(DomainService::class));
        $this->set('dateHelper', $this->app->make('date'));
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
        $this->set('challengeTypes', $this->app->make(ChallengeTypeManager::class)->getChallengeTypes());
        $this->set('elementManager', $this->app->make(ElementManager::class));
        $this->set('page', $this->getPageObject());
        $this->set('pageTitle', $domain->getID() === null ? t('Add domain') : t('Edit domain'));
        $this->requireAsset('javascript', 'vue');
    }

    public function check_deviation()
    {
        if (!$this->token->validate('acme-check-deviation')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $rf = $this->app->make(ResponseFactoryInterface::class);
        $hostname = $this->request->request->get('hostname');
        try {
            $domainName = DomainName::fromName($hostname);
        } catch (IDNAException $x) {
            return $rf->json($x->getMessage());
        }
        if ($domainName->isDeviated()) {
            return $rf->json([
                'name' => $domainName->getDeviatedName(),
                'punycode' => $domainName->getDeviatedPunycode(),
            ]);
        }

        return $rf->json(false);
    }

    public function submit($domainID = '', $accountID = '')
    {
        if ($domainID === 'new') {
            $accountID = (int) $accountID;
            $account = $accountID === 0 ? null : $this->app->make(EntityManagerInterface::class)->find(Account::class, $accountID);
            if ($account === null) {
                return $this->buildReturnRedirectResponse();
            }
            $domain = null;
        } else {
            $domain = $this->getDomain($domainID);
            if ($domain === null) {
                return $this->buildReturnRedirectResponse();
            }
        }
        if (!$this->token->validate("acme-domain-edit-{$domainID}-{$accountID}")) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view($domainID, $accountID);
        }
        $data = $this->getDataForEditor();
        $editor = $this->app->make(DomainEditor::class);
        if ($domain === null) {
            $domain = $editor->create($account, $data, $this->error);
        } else {
            $editor->edit($domain, $data, $this->error);
        }
        if ($this->error->has()) {
            return $this->view($domainID, $accountID);
        }
        $this->flash('success', $domainID === 'new' ? t('The domain has been created') : t('The domain has been updated'));

        return $this->buildReturnRedirectResponse();
    }

    public function delete($id = '')
    {
        $domain = $this->getDomain($id);
        if ($domain === null) {
            return $this->buildReturnRedirectResponse();
        }
        if (!$this->token->validate('acme-domain-delete-' . $domain->getID())) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view($id);
        }
        $editor = $this->app->make(DomainEditor::class);
        if (!$editor->delete($domain, $this->error)) {
            return $this->view($id);
        }

        $this->flash('success', t('The domain has been deleted.'));

        return $this->buildReturnRedirectResponse();
    }

    /**
     * @param int|string $domainID
     * @param int|string|null $accountID (used when $domainID === 'new')
     * @param bool $flashOnNotFound
     *
     * @return \Acme\Entity\Domain|null
     */
    private function getDomain($domainID, $accountID = null, $flashOnNotFound = true)
    {
        if ($domainID === 'new') {
            $accountID = (int) $accountID;
            $account = $accountID === 0 ? null : $this->app->make(EntityManagerInterface::class)->find(Account::class, $accountID);

            return $account === null ? null : Domain::create($account);
        }
        $domainID = (int) $domainID;
        $domain = $domainID === 0 ? null : $this->app->make(EntityManagerInterface::class)->find(Domain::class, $domainID);
        if ($domain !== null) {
            return $domain;
        }
        if ($domainID !== 0 && $flashOnNotFound) {
            $this->flash('error', t('Unable to find the requested domain.'));
        }

        return null;
    }

    /**
     * @return \Concrete\Core\Routing\RedirectResponse
     */
    private function buildReturnRedirectResponse()
    {
        return $this->app->make(ResponseFactoryInterface::class)->redirect(
            $this->app->make(ResolverManagerInterface::class)->resolve(['/dashboard/system/acme/domains']),
            302
        );
    }

    /**
     * @return array
     */
    private function getDataForEditor()
    {
        $data = $this->request->request->all();
        $t = $this->token;
        unset($data[$t::DEFAULT_TOKEN_NAME]);
        $challengeTypeConfigurations = array_get($data, 'challengetypeconfiguration');
        if (!is_array($challengeTypeConfigurations)) {
            return $data;
        }
        unset($data['challengetypeconfiguration']);
        $challengeTypeHandle = (string) array_get($data, 'challengetype');
        if ($challengeTypeHandle === '') {
            return $data;
        }
        if (!isset($challengeTypeConfigurations[$challengeTypeHandle])) {
            return $data;
        }
        if (!is_array($challengeTypeConfigurations[$challengeTypeHandle])) {
            return $data;
        }

        return $data + $challengeTypeConfigurations[$challengeTypeHandle];
    }
}
