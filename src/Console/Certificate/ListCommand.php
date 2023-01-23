<?php

namespace Acme\Console\Certificate;

use Acme\Entity\Account;
use Acme\Entity\Certificate;
use Acme\Entity\Server;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
use Concrete\Core\Console\Command;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\Table;

defined('C5_EXECUTE') or die('Access Denied.');

final class ListCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'List the HTTPS certificates for HTTPS certificates, or get the details about a specific certificate.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:certificate:list
    {certificate? : show the details about a specific certificate - use its ID}
    {--s|server= : limit the list to a specific ACME server - use either its ID or name}
    {--a|account= : limit the list to a specific ACME account - use either its ID or name}
EOT
    ;

    public function handle(EntityManagerInterface $em, Finder $finder)
    {
        $id = $this->input->getArgument('certificate');
        $serverIDOrName = $this->input->getOption('server');
        $accountIDOrName = $this->input->getOption('account');
        if ($id === null) {
            if ($serverIDOrName !== null) {
                if ($accountIDOrName !== null) {
                    $this->output->error("Please don't specify both the --server and the --account options");

                    return 1;
                }
                try {
                    $server = $finder->findServer($serverIDOrName);
                } catch (EntityNotFoundException $x) {
                    $this->output->error($x->getMessage());

                    return 1;
                }
                $account = null;
            } elseif ($accountIDOrName !== null) {
                try {
                    $account = $finder->findAccount($accountIDOrName);
                } catch (EntityNotFoundException $x) {
                    $this->output->error($x->getMessage());

                    return 1;
                }
                $server = $account->getServer();
            } else {
                $server = null;
                $account = null;
            }

            return $this->listCertificates($em, $server, $account);
        }

        if ($serverIDOrName !== null || $accountIDOrName !== null) {
            $this->output->error("If you specify the 'certificate' argument, please don't use the '--server' / '--account' options");

            return 1;
        }

        if ($id !== (string) (int) $id) {
            $this->output->error("Please specify an integer number for the 'certificate' argument");

            return 1;
        }
        $certificate = $em->find(Certificate::class, (int) $id);
        if ($certificate === null) {
            $this->output->error("Unable to find a certificate with ID {$id}");

            return 1;
        }

        return $this->showCertificateDetails($certificate);
    }

    private function listCertificates(EntityManagerInterface $em, Server $server = null, Account $account = null)
    {
        $table = new Table($this->output);
        $table
            ->setHeaders([
                'ID',
                'Disabled',
                'Account',
                'Server',
                'Domains',
                'Valid from',
                'Valid to',
                'Issuer',
            ])
        ;
        $qb = $em->createQueryBuilder();
        $qb
            ->from(Certificate::class, 'c')
            ->select('c')
            ->orderBy('c.id', 'ASC')
        ;
        if ($account !== null) {
            $qb->andWhere($qb->expr()->eq('c.account', ':account'))->setParameter('account', $account);
        } elseif ($server !== null) {
            $qb
                ->innerJoin('c.account', 'a')
                ->andWhere($qb->expr()->eq('a.server', ':server'))->setParameter('server', $server);
        }
        foreach ($qb->getQuery()->execute() as $certificate) {
            $info = $certificate->getCertificateInfo();
            $table->addRow([
                $certificate->getID(),
                $certificate->isDisabled() ? 'yes' : 'no',
                $certificate->getAccount()->getServer()->getName(),
                $certificate->getAccount()->getName(),
                implode("\n", $certificate->getDomainHostDisplayNames()),
                $info === null ? '' : $info->getStartDate()->format('c'),
                $info === null ? '' : $info->getEndDate()->format('c'),
                $info === null ? '' : $info->getIssuerName(),
            ]);
        }
        $table->render();

        return 0;
    }

    private function showCertificateDetails(Certificate $certificate)
    {
        $primaryDomain = null;
        $otherDomains = [];
        foreach ($certificate->getDomains() as $certificateDomain) {
            if ($primaryDomain === null) {
                $primaryDomain = $certificateDomain->getDomain()->getHostDisplayName();
            } else {
                $otherDomains[] = $certificateDomain->getDomain()->getHostDisplayName();
            }
        }
        $info = $certificate->getCertificateInfo();
        $keyPair = $certificate->getKeyPair();

        $this->output->writeln([
            'ID                 : ' . $certificate->getID(),
            'Disabled           : ' . ($certificate->isDisabled() ? 'yes' : 'no'),
            'Created on         : ' . $certificate->getCreatedOn()->format('c'),
            'Primary domain     : ' . $primaryDomain,
            'Other domains      : ' . implode(' ', $otherDomains),
            'Private key size   : ' . ($keyPair === null ? '' : "{$keyPair->getPrivateKeySize()} bits"),
            'Account            : ' . $certificate->getAccount()->getName(),
            'Server             : ' . $certificate->getAccount()->getServer()->getName(),
            'CSR created        : ' . ($certificate->getCsr() === '' ? 'No' : 'Yes'),
            'Issuance pending   : ' . ($certificate->getOngoingOrder() === null ? 'No' : 'Yes'),
            'Valid from         : ' . ($info === null ? '(certificate not yet issued)' : $info->getStartDate()->format('c')),
            'Valid to           : ' . ($info === null ? '(certificate not yet issued)' : $info->getEndDate()->format('c')),
            'Issuer name        : ' . ($info === null ? '(certificate not yet issued)' : $info->getIssuerName()),
            'OCSP responder URL : ' . ($info === null ? '(certificate not yet issued)' : $info->getOcspResponderUrl()),
        ]);

        return 0;
    }
}
