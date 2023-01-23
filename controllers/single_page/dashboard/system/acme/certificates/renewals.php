<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme\Certificates;

use Acme\Entity\Certificate;
use Acme\Entity\Order;
use Acme\Service\UI;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

final class Renewals extends DashboardPageController
{
    public function view($certificateID = '')
    {
        $certificate = $this->getCertificate($certificateID);
        if ($certificate === null) {
            return $this->buildReturnRedirectResponse();
        }
        $this->set('certificate', $certificate);
        $this->set('pageTitle', t('Renewal list for the certificate for %s', implode(', ', $certificate->getDomainHostDisplayNames())));
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
        $this->set('dateHelper', $this->app->make('date'));
        $this->set('ui', $this->app->make(UI::class));
    }

    public function clear_history($certificateID = '')
    {
        $certificate = $this->getCertificate($certificateID);
        if ($certificate === null) {
            return $this->buildReturnRedirectResponse();
        }
        if (!$this->token->validate('acme-certificate-clear_history-' . $certificateID)) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view($certificateID);
        }
        $em = $this->app->make(EntityManagerInterface::class);

        $qb = $em->createQueryBuilder();
        $qb
            ->delete(Order::class, 'o')
            ->andWhere($qb->expr()->eq('o.certificate', ':certificate'))
            ->setParameter('certificate', $certificate)
        ;
        if ($certificate->getOngoingOrder() !== null) {
            $qb
                ->andWhere($qb->expr()->neq('o', ':ongoingOrder'))
                ->setParameter('ongoingOrder', $certificate->getOngoingOrder())
            ;
        }
        $qb->getQuery()->execute();
        $this->flash('success', t('The certificate renewal history has been cleared.'));

        return $this->app->make(ResponseFactoryInterface::class)->redirect(
            $this->app->make(ResolverManagerInterface::class)->resolve(['/dashboard/system/acme/certificates/renewals', $certificateID]),
            302
        );
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
