<?php

namespace Acme\Console\Account;

use Acme\Entity\Account;
use Acme\Entity\Server;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
use Acme\Security\Crypto;
use Concrete\Core\Console\Command;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\Table;

defined('C5_EXECUTE') or die('Access Denied.');

class ListCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'List the ACME accounts, or get the details about a specific ACME account.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:account:list
    {account? : show the details about a specific account - use either its ID or name}
    {--s|server= : limit the list to a specific ACME server - use either its ID or name}
EOT
    ;

    public function handle(EntityManagerInterface $em, Finder $finder, Crypto $crypto)
    {
        $idOrName = $this->input->getArgument('account');
        if ($idOrName === null) {
            $serverIDOrName = $this->input->getOption('server');
            if ($serverIDOrName === null) {
                $server = null;
            } else {
                try {
                    $server = $finder->findServer($serverIDOrName);
                } catch (EntityNotFoundException $x) {
                    $this->output->error($x->getMessage());

                    return 1;
                }
            }

            return $this->listAccounts($em, $server);
        }

        if ($this->input->getOption('server') !== null) {
            $this->output->error("If you specify the 'account' argument, please don't use the '--server' option");

            return 1;
        }

        try {
            $account = $finder->findAccount($idOrName);
        } catch (EntityNotFoundException $x) {
            $this->output->error($x->getMessage());

            return 1;
        }

        return $this->showAccountDetails($account, $crypto);
    }

    protected function listAccounts(EntityManagerInterface $em, Server $server = null)
    {
        $table = new Table($this->output);
        $table
            ->setHeaders([
                'Default',
                'ID',
                'Name',
                'Server',
                'Domains',
            ])
        ;
        $qb = $em->createQueryBuilder();
        $qb
            ->from(Account::class, 'a')
            ->select('a')
            ->orderBy('a.name', 'ASC')
            ->addOrderBy('a.id', 'ASC')
        ;
        if ($server !== null) {
            $qb->andWhere($qb->expr()->eq('a.server', ':server'))->setParameter('server', $server);
        }
        foreach ($qb->getQuery()->execute() as $account) {
            $table->addRow([
                $account->isDefault() ? '*' : '',
                $account->getID(),
                $account->getName(),
                $account->getServer()->getName(),
                $account->getDomains()->count(),
            ]);
        }
        $table->render();

        return 0;
    }

    protected function showAccountDetails(Account $account, Crypto $crypto)
    {
        $this->output->writeln([
            'ID                 : ' . $account->getID(),
            'Default            : ' . ($account->isDefault() ? 'Yes' : 'No'),
            'Name               : ' . $account->getName(),
            'Email              : ' . $account->getEmail(),
            'Server             : ' . $account->getServer()->getName(),
            'Created on         : ' . $account->getCreatedOn()->format('c'),
            'Private key size   : ' . $crypto->getKeySize($account->getKeyPair()) . ' bits',
        ]);

        return 0;
    }
}
