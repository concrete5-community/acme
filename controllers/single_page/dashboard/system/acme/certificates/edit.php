<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme\Certificates;

use Acme\Crypto\FileDownloader;
use Acme\Editor\CertificateEditor;
use Acme\Entity\Account;
use Acme\Entity\Certificate;
use Acme\Entity\Domain;
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
    public function view($certificateID = '', $accountID = '')
    {
        $certificate = $this->getCertificate($certificateID, $accountID);
        if ($certificate === null) {
            return $this->buildReturnRedirectResponse();
        }
        $this->set('certificate', $certificate);
        $em = $this->app->make(EntityManagerInterface::class);
        $applicableDomains = $em->getRepository(Domain::class)->findBy(['account' => $certificate->getAccount()], ['hostname' => 'ASC', 'isWildcard' => 'ASC']);
        $this->set('applicableDomains', $applicableDomains);
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
        $config = $this->app->make('config');
        $this->set('defaultKeySize', (int) $config->get('acme::security.key_size.default'));
        $this->set('minimumKeySize', (int) $config->get('acme::security.key_size.min'));
        $this->set('pageTitle', $certificate->getID() === null ? t('Add HTTPS certificate') : t('Edit HTTPS certificate'));
        $this->set('ui', $this->app->make(UI::class));
        $this->addHeaderItem(
            <<<'EOT'
<style>
table#acme-certificate-domains>tbody>tr.domain-selected-0>td>label {
    font-weight: normal;
}
table#acme-certificate-domains>tbody>tr.domain-selected-0>td>input[type="radio"] {
    display: none;
}
table#acme-certificate-domains>tbody>tr.domain-selected-1>td>label {
    font-weight: bold;
}
</style>
EOT
        );
    }

    public function submit($certificateID = '', $accountID = '')
    {
        if ($certificateID === 'new') {
            $accountID = (int) $accountID;
            $account = $accountID === 0 ? null : $this->app->make(EntityManagerInterface::class)->find(Account::class, $accountID);
            if ($account === null) {
                return $this->buildReturnRedirectResponse();
            }
            $certificate = null;
        } else {
            $certificate = $this->getCertificate($certificateID, $accountID);
            if ($certificate === null) {
                return $this->buildReturnRedirectResponse();
            }
            $account = $certificate->getAccount();
        }
        if (!$this->token->validate('acme-certificate-edit-' . ($certificate === null ? 'new' : $certificate->getID()) . '-' . $account->getID())) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view($certificateID, $accountID);
        }

        $data = $this->request->request->all();
        $t = $this->token;
        unset($data[$t::DEFAULT_TOKEN_NAME]);

        $editor = $this->app->make(CertificateEditor::class);
        if ($certificate === null) {
            $certificate = $editor->create($account, $data, $this->error);
        } else {
            $editor->edit($certificate, $data, $this->error);
        }
        if ($this->error->has()) {
            return $this->view($certificateID, $accountID);
        }
        $this->flash('success', $certificateID === 'new' ? t('The certificate has been created') : t('The certificate has been updated'));

        return $this->buildReturnRedirectResponse();
    }

    public function download_key($id = '')
    {
        $certificate = $this->getCertificate($id);
        if ($certificate === null) {
            return $this->buildReturnRedirectResponse();
        }
        if (!$this->token->validate('acme-download-certificate-key-' . $certificate->getID())) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view($id);
        }
        $certificateInfo = $certificate->getCertificateInfo();
        $downloader = $this->app->make(FileDownloader::class);
        try {
            return $downloader->download(
                $this->request->request->get('what'),
                $this->request->request->get('format'),
                [
                    FileDownloader::WHAT_CSR => $certificate->getCsr(),
                    FileDownloader::WHAT_CERTIFICATE => $certificateInfo === null ? '' : $certificateInfo->getCertificate(),
                    FileDownloader::WHAT_ISSUERCERTIFICATE => $certificateInfo === null ? '' : $certificateInfo->getIssuerCertificate(),
                    FileDownloader::WHAT_PRIVATEKEY => $certificate->getPrivateKey(),
                ],
                t('Key for certificate with ID %s', $certificate->getID())
            );
        } catch (FileDownloaderException $x) {
            $this->error->add($x->getMessage());

            return $this->view($id);
        }
    }

    public function delete($id = '')
    {
        $certificate = $this->getCertificate($id);
        if ($certificate === null) {
            return $this->buildReturnRedirectResponse();
        }
        if (!$this->token->validate('acme-certificate-delete-' . $certificate->getID())) {
            throw new UserMessageException($this->token->getErrorMessage());
        }

        $editor = $this->app->make(CertificateEditor::class);
        if (!$editor->delete($certificate, $this->error)) {
            return $this->view($id);
        }

        $this->flash('success', t('The certificate has been removed'));

        return $this->buildReturnRedirectResponse();
    }

    /**
     * @param int|string $certificateID
     * @param int|string|null $accountID (used when $certificateID === 'new')
     * @param bool $flashOnNotFound
     *
     * @return \Acme\Entity\Certificate|null
     */
    private function getCertificate($certificateID, $accountID = null, $flashOnNotFound = true)
    {
        if ($certificateID === 'new') {
            $accountID = (int) $accountID;
            $account = $accountID === 0 ? null : $this->app->make(EntityManagerInterface::class)->find(Account::class, $accountID);

            return $account === null ? null : Certificate::create($account);
        }
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
