<?php

namespace Acme\Console\Domain;

use Acme\ChallengeType\ChallengeTypeManager;
use Acme\Entity\Account;
use Acme\Entity\Domain;
use Acme\Entity\Server;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
use Concrete\Core\Console\Command as CoreCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\Table;

defined('C5_EXECUTE') or die('Access Denied.');

final class ListCommand extends CoreCommand
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'List the domains for HTTPS certificates, or get the details about a specific domain.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:domain:list
    {domain? : show the details about a specific domain - use either its ID or its host name}
    {--s|server= : limit the list to a specific ACME server - use either its ID or name}
    {--a|account= : limit the list to a specific ACME account - use either its ID or name}
EOT
    ;

    public function handle(EntityManagerInterface $em, Finder $finder, ChallengeTypeManager $challengeTypeManager)
    {
        $idOrName = $this->input->getArgument('domain');
        $serverIDOrName = $this->input->getOption('server');
        $accountIDOrName = $this->input->getOption('account');
        if ($idOrName === null) {
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

            return $this->listDomains($em, $server, $account);
        }

        if ($serverIDOrName !== null || $accountIDOrName !== null) {
            $this->output->error("If you specify the 'domain' argument, please don't use the '--server' / '--account' options");

            return 1;
        }

        try {
            $domain = $finder->findDomain($idOrName);
        } catch (EntityNotFoundException $x) {
            $this->output->error($x->getMessage());

            return 1;
        }

        return $this->showDomainDetails($domain, $challengeTypeManager);
    }

    private function listDomains(EntityManagerInterface $em, Server $server = null, Account $account = null)
    {
        $table = new Table($this->output);
        $table
            ->setHeaders([
                'ID',
                'Wildcard?',
                'Hostname',
                'Punycode',
                'Authorization method',
                'Account',
                'Server',
                'Certificates',
            ])
        ;
        $qb = $em->createQueryBuilder();
        $qb
            ->from(Domain::class, 'd')
            ->select('d')
            ->orderBy('d.hostname', 'ASC')
            ->addOrderBy('d.isWildcard', 'ASC')
        ;
        if ($account !== null) {
            $qb->andWhere($qb->expr()->eq('d.account', ':account'))->setParameter('account', $account);
        } elseif ($server !== null) {
            $qb
                ->innerJoin('d.account', 'a')
                ->andWhere($qb->expr()->eq('a.server', ':server'))->setParameter('server', $server);
        }
        foreach ($qb->getQuery()->execute() as $domain) {
            $table->addRow([
                $domain->getID(),
                $domain->isWildcard() ? 'Yes' : 'No',
                $domain->getHostname(),
                $domain->getPunycode(),
                $domain->getChallengeTypeHandle(),
                $domain->getAccount()->getName(),
                $domain->getAccount()->getServer()->getName(),
                $domain->getCertificates()->count(),
            ]);
        }
        $table->render();

        return 0;
    }

    private function showDomainDetails(Domain $domain, ChallengeTypeManager $challengeTypeManager)
    {
        $challengeType = $challengeTypeManager->getChallengeByHandle($domain->getChallengeTypeHandle());
        $this->output->writeln([
            'ID                 : ' . $domain->getID(),
            'Wildcard           : ' . ($domain->isWildcard() ? 'Yes' : 'No'),
            'Host name          : ' . $domain->getHostname(),
            'Punycode           : ' . $domain->getPunycode(),
            'Host name (full)   : ' . $domain->getHostDisplayName(),
            'Server             : ' . $domain->getAccount()->getServer()->getName(),
            'Account            : ' . $domain->getAccount()->getName(),
            'Created on         : ' . $domain->getCreatedOn()->format('c'),
            'Authorization type : ' . ($domain->getChallengeTypeHandle() . ($challengeType === null ? '' : " ({$challengeType->getName()})")),
            'Certificates       : ' . $domain->getCertificates()->count(),
        ]);

        return 0;
    }
}
