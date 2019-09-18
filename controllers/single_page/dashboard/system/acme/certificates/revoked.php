<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme\Certificates;

use Acme\Entity\Certificate;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Acme\Entity\RevokedCertificate;
use Acme\Security\FileDownloader;
use Acme\Exception\FileDownloaderException;

defined('C5_EXECUTE') or die('Access Denied.');

class Revoked extends DashboardPageController
{
    public function view($certificateID = '')
    {
        if ((string) $certificateID === 'unlinked') {
            $certificate = null;
        } else {
            $certificate = $this->getCertificate($certificateID);
            if ($certificate === null) {
                return $this->buildReturnRedirectResponse();
            }
        }
        $this->set('certificate', $certificate);
        if ($certificate === null) {
            $em = $this->app->make(EntityManagerInterface::class);
            $qb = $em->createQueryBuilder();
            $qb
                ->select('r')
                ->from(RevokedCertificate::class, 'r')
                ->andWhere(
                    $qb->expr()->isNull('r.parentCertificate')
                )
            ;
            $revokedCertificates = $qb->getQuery()->execute();
        } else {
            $this->set('pageTitle', t('Revoked certificates for %s', implode(', ', $certificate->getDomainHostDisplayNames())));
            $revokedCertificates = $certificate->getRevokedCertificates()->toArray();
        }
        $this->set('revokedCertificates', $revokedCertificates);
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
        $this->set('dateHelper', $this->app->make('date'));
    }

    public function download_key($certificateID = '', $revokedCertificateID = '')
    {
        if ((string) $certificateID === 'unlinked') {
            $certificate = null;
        } else {
            $certificate = $this->getCertificate($certificateID);
            if ($certificate === null) {
                return $this->buildReturnRedirectResponse();
            }
        }
        $revokedCertificateID = (int) $revokedCertificateID;
        $revokedCertificate = $revokedCertificateID === 0 ? null : $this->app->make(EntityManagerInterface::class)->find(RevokedCertificate::class, $revokedCertificateID);
        if ($revokedCertificate === null) {
            $this->error->add(t('Unable to find the requested revoked certificate'));

            return $this->view($certificateID);
        }
        if (!$this->token->validate('acme-download-revokedcertificate-key-' . $revokedCertificate->getID())) {
            $this->error->add($this->token->getErrorMessage());

            return $this->view($certificateID);
        }
        $downloader = $this->app->make(FileDownloader::class);
        try {
            return $downloader->download(
                $this->request->request->get('what'),
                $this->request->request->get('format'),
                [
                    FileDownloader::WHAT_CERTIFICATE => $revokedCertificate->getCertificate(),
                    FileDownloader::WHAT_ISSUERCERTIFICATE =>$revokedCertificate->getIssuerCertificate(),
                ],
                t('Key for revoked certificate with ID %s', $revokedCertificate->getID())
            );
        } catch (FileDownloaderException $x) {
            $this->error->add($x->getMessage());

            return $this->view($certificateID);
        }
    }

    public function delete($certificateID = '')
    {
        if ((string) $certificateID === 'unlinked') {
            $certificate = null;
        } else {
            $certificate = $this->getCertificate($certificateID);
            if ($certificate === null) {
                return $this->buildReturnRedirectResponse();
            }
        }
        if (!$this->token->validate('acme-certificate-clear_history-' . ($certificate === null ? 'unlinked' : $certificate->getID()))) {
            $this->error->add($this->token->getErrorMessage());
            return $this->view($certificateID);
        }
        $em = $this->app->make(EntityManagerInterface::class);
        $qb = $em->createQueryBuilder();
        $qb->delete(RevokedCertificate::class, 'rc');
        if ($certificate === null) {
            $qb->andWhere($qb->expr()->isNull('rc.parentCertificate'));
        } else {
            $qb
                ->andWhere($qb->expr()->eq(
                    'rc.parentCertificate',
                    ':parentCertificate'
                ))
                ->setParameter('parentCertificate', $certificate)
            ;
        }
        $qb->getQuery()->execute();

        $this->flash('success', t('The revoked certificates have been deleted'));

        return $this->app->make(ResponseFactoryInterface::class)->redirect(
            $this->app->make(ResolverManagerInterface::class)->resolve(['/dashboard/system/acme/certificates/revoked', $certificateID]),
            302
        );
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
