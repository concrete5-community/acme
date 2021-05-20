<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme;

use Acme\Certificate\Renewer;
use Acme\Entity\Account;
use Acme\Entity\Certificate;
use Acme\Entity\Server;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Punic\Comparer;

defined('C5_EXECUTE') or die('Access Denied.');

class Certificates extends DashboardPageController
{
    public function view()
    {
        $cmp = new Comparer();
        $em = $this->app->make(EntityManagerInterface::class);
        $qb = $em->createQueryBuilder();
        $qb
            ->from(Account::class, 'a')
            ->innerJoin('a.domains', 'd')
            ->select('a')
            ->distinct()
        ;
        $accounts = $qb->getQuery()->getResult();
        usort(
            $accounts,
            function (Account $a, Account $b) use ($cmp) {
                return $cmp->compare($a->getName(), $b->getName());
            }
        );
        $this->set('accounts', $accounts);
        $servers = [];
        foreach ($accounts as $account) {
            if (!in_array($account->getServer(), $servers, true)) {
                $servers[] = $account->getServer();
            }
        }
        usort(
            $servers,
            function (Server $a, Server $b) use ($cmp) {
                return $cmp->compare($a->getName(), $b->getName());
            }
        );
        $this->set('servers', $servers);
        $certificates = $em->getRepository(Certificate::class)->findBy([], ['createdOn' => 'ASC']);
        $this->set('certificates', $certificates);
        $this->set('resolverManager', $this->app->make(ResolverManagerInterface::class));
        $this->set('dateHelper', $this->app->make('date'));
        $this->set('renewer', $this->app->make(Renewer::class));
        $this->addHeaderItem($this->getCSS());
    }

    public function set_certificate_disabled()
    {
        if (!$this->token->validate('acme-setcertificate-disabled')) {
            throw new UserMessageException($this->token->getErrorMessage());
        }
        $certificateID = (int) $this->request->request('certificate', 0);
        $em = $this->app->make(EntityManagerInterface::class);
        $certificate = $certificateID === 0 ? null : $em->find(Certificate::class, $certificateID);
        if ($certificate === null) {
            throw new UserMessageException(t('Unable to find the requested certificate.'));
        }
        switch ($this->request->request->get('disable')) {
            case '0':
                $newValue = false;
                break;
            case '1':
                $newValue = true;
                break;
            default:
                $newValue = $certificate->isDisabled();
                break;
        }
        if ($newValue !== $certificate->isDisabled()) {
            $certificate->setDisabled($newValue);
            $em->flush($certificate);
        }

        return $this->app->make(ResponseFactoryInterface::class)->json($newValue);
    }

    private function getCSS()
    {
        return <<<'EOT'
<style>
tr.certificate-disabled .disable-certificate {
    display: none;
}
tr.certificate-disabled .certificate-operation {
    display: none;
}

tr.certificate-enabled .enable-certificate {
    display: none;
}
</style>
EOT
        ;
    }
}
