<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme\Certificates;

use Acme\Certificate\Renewer;
use Acme\Certificate\RenewerOptions;
use Acme\Entity\Certificate;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class Operate extends DashboardPageController
{
    public function view($certificateID = '')
    {
        $certificate = $this->getCertificate($certificateID);
        if ($certificate === null) {
            return $this->buildReturnRedirectResponse();
        }
        $this->set('certificate', $certificate);
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
        $this->requireAsset('moment');
        $this->requireAsset('javascript', 'vue');
    }

    public function next_step($certificateID = '')
    {
        if (!$this->request->isXmlHttpRequest()) {
            return $this->buildReturnRedirectResponse();
        }
        $certificate = $this->getCertificate($certificateID);
        if ($certificate === null) {
            throw new UserMessageException(t('Unable to find the requested certificate.'));
        }
        if (!$this->token->validate('acme-certificate-nextstep-' . $certificateID)) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $post = $this->request->request;
        $renewerOptions = RenewerOptions::create()
            ->setForceCertificateRenewal($post->get('forceRenew'))
            ->setForceActionsExecution($post->get('forceActions'))
        ;
        $renewer = $this->app->make(Renewer::class);
        $renewed = $renewer->nextStep($certificate, $renewerOptions);

        $responseData = [
            'messages' => $renewed->getEntries(),
            'nextStepAfter' => $renewed->getNextStepAfter(),
        ];
        if ($renewed->getNewCertificateInfo() !== null) {
            $responseData['certificateInfo'] = $renewed->getNewCertificateInfo();
        }
        if ($renewed->getOrderOrAuthorizationsRequest() !== null) {
            $responseData['order'] = $renewed->getOrderOrAuthorizationsRequest();
        }

        return $this->app->make(ResponseFactoryInterface::class)->json($responseData);
    }

    /***
     * @param mixed $certificateID
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
