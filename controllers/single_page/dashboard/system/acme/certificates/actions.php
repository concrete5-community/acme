<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme\Certificates;

use Acme\Editor\CertificateActionEditor;
use Acme\Entity\Certificate;
use Acme\Entity\CertificateAction;
use Acme\Entity\RemoteServer;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class Actions extends DashboardPageController
{
    public function view($certificateID = '')
    {
        $certificate = $this->getCertificate($certificateID);
        if ($certificate === null) {
            return $this->buildReturnRedirectResponse();
        }
        $this->set('certificate', $certificate);
        $this->set('actions', $certificate->getActions()->toArray());
        $this->set('remoteServers', $this->app->make(EntityManagerInterface::class)->getRepository(RemoteServer::class)->findBy([], ['name' => 'ASC']));
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
        $this->requireAsset('javascript', 'vue');
        $this->addHeaderItem(
            <<<'EOT'
<style>
#acme-certificate-actions tbody>tr>td:first-child {
    width: 1px;
    white-space: nowrap;
}
#acme-certificate-actions input[type="text"] {
    width: 100%;
}
</style>
EOT
        );
    }

    public function save_action($certificateID = '')
    {
        if (!$this->token->validate('acme-removeaction-' . $certificateID)) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $certificate = $this->getCertificate($certificateID, false);
        if ($certificate === null) {
            throw new UserMessageException(t('Unable to find the requested certificate.'));
        }
        $data = $this->request->request->all();
        $t = $this->token;
        unset($data[$t::DEFAULT_TOKEN_NAME]);
        $actionID = (int) array_get($data, 'id');
        unset($data['id'], $data['certificate']);

        $editor = $this->app->make(CertificateActionEditor::class);
        if ($actionID === 0) {
            $action = $editor->create($certificate, $data, $this->error);
            if ($action === null) {
                throw new UserMessageException($this->error->toText());
            }
        } else {
            $action = $this->app->make(EntityManagerInterface::class)->getRepository(CertificateAction::class)->findOneBy(['id' => $actionID, 'certificate' => $certificate->getID()]);
            if ($action === null) {
                throw new UserMessageException(t('Unable to find the requested action'));
            }
            if ($editor->edit($action, $data, $this->error) === false) {
                throw new UserMessageException($this->error->toText());
            }
        }

        return $this->app->make(ResponseFactoryInterface::class)->json($action);
    }

    public function remove_action($certificateID = '')
    {
        if (!$this->token->validate('acme-removeaction-' . $certificateID)) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $certificate = $this->getCertificate($certificateID, false);
        if ($certificate === null) {
            throw new UserMessageException(t('Unable to find the requested certificate.'));
        }
        $actionID = (int) $this->request->request->get('id');
        $action = $actionID === 0 ? null : $this->app->make(EntityManagerInterface::class)->getRepository(CertificateAction::class)->findOneBy(['id' => $actionID, 'certificate' => $certificate->getID()]);
        if ($action === null) {
            throw new UserMessageException(t('Unable to find the requested action'));
        }
        $editor = $this->app->make(CertificateActionEditor::class);
        if (!$editor->delete($action, $this->error)) {
            throw new UserMessageException($this->error->toText());
        }

        return $this->app->make(ResponseFactoryInterface::class)->json(true);
    }

    /**
     * @param int|string $certificateID
     * @param bool $flashOnNotFound
     *
     * @return \Acme\Entity\Certificate|null
     */
    private function getCertificate($certificateID, $flashOnNotFound = true)
    {
        $certificateID = (int) $certificateID;
        $certificate = $certificateID === 0 ? null : $this->app->make(EntityManagerInterface::class)->find(Certificate::class, $certificateID);
        if ($certificate !== null) {
            return $certificate;
        }
        if ($certificateID !== 0 && $flashOnNotFound) {
            $this->flash('error', t('Unable to find the requested certificate.'));
        }

        return null;
    }

    /**
     * @return \Concrete\Core\Routing\RedirectResponse
     */
    private function buildReturnRedirectResponse()
    {
        return $this->app->make(ResponseFactoryInterface::class)->redirect(
            $this->app->make(ResolverManagerInterface::class)->resolve(['/dashboard/system/acme/certificates']),
            302
        );
    }
}
