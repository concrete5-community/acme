<?php

namespace Acme\Console\Domain;

use Acme\Editor\DomainEditor;
use Acme\Entity\Domain;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
use Concrete\Core\Console\Command as CoreCommand;
use Concrete\Core\Error\ErrorList\ErrorList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

defined('C5_EXECUTE') or die('Access Denied.');

class DeleteCommand extends CoreCommand
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'Delete existing Domain instances.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:domain:delete
    {domain? : The host name, or the ID, of an existing domain}
    {--a|all : Delete ALL the domains}
    {--s|server= : The mnemonic name, or the ID, of the ACME Server - used with the --all option}
    {--c|account= : The mnemonic name, or the ID, of the ACME Account - used with the --all option}
    {--f|force : Don't ask confirmation}
EOT
    ;

    public function handle(DomainEditor $domainEditor, EntityManagerInterface $em, Finder $finder)
    {
        $nameOrID = $this->input->getArgument('domain');
        $all = $this->input->getOption('all');
        $serverNameOrID = $this->input->getOption('server');
        $accountNameOrID = $this->input->getOption('account');
        if ($nameOrID !== null) {
            if ($all === true || $serverNameOrID !== null || $accountNameOrID !== null) {
                $this->output->error("If you specify the 'domain' argument, you can't specify the --all / --server / --account options");

                return 1;
            }
            try {
                $domain = $finder->findDomain($nameOrID);
            } catch (EntityNotFoundException $x) {
                $this->output->error($x->getMessage());

                return 1;
            }

            return $this->deleteDomain($domain, $domainEditor) ? 1 : 0;
        }
        if ($all === false) {
            $this->output->error("Neither the 'domain' argument, nor the '--all' option has been specified");

            return 1;
        }
        if ($accountNameOrID !== null) {
            if ($serverNameOrID !== null) {
                $this->output->error("Please don't specify both the --server and the --account options");

                return 1;
            }
            try {
                $account = $finder->findAccount($accountNameOrID);
            } catch (EntityNotFoundException $x) {
                $this->output->error($x->getMessage());

                return 1;
            }
            $server = $account->getServer();
        } elseif ($serverNameOrID !== null) {
            try {
                $server = $finder->findServer($serverNameOrID);
            } catch (EntityNotFoundException $x) {
                $this->output->error($x->getMessage());

                return 1;
            }
            $account = null;
        } else {
            $server = null;
            $account = null;
        }

        $qb = $em->createQueryBuilder();
        $qb
            ->from(Domain::class, 'd')
            ->select('d')
        ;
        if ($account !== null) {
            $qb->andWhere($qb->expr()->eq('d.account', ':account'))->setParameter('account', $account);
        } elseif ($server !== null) {
            $qb
                ->innerJoin('d.account', 'a')
                ->andWhere($qb->expr()->eq('a.server', ':server'))->setParameter('server', $server)
            ;
        }
        $numDeleted = 0;
        $numNotDeleted = 0;
        $numSkipped = 0;
        foreach ($qb->getQuery()->execute() as $domain) {
            $deleted = $this->deleteDomain($domain, $domainEditor);
            if ($deleted === true) {
                ++$numDeleted;
            } elseif ($deleted === false) {
                ++$numDeleted;
            } else {
                ++$numSkipped;
            }
        }

        $this->output->writeln("Number of DELETED domains: {$numDeleted}");
        if ($numNotDeleted !== 0) {
            $this->output->error("Number of NOT deleted domains: {$numNotDeleted}");
        }
        if ($numSkipped !== 0) {
            $this->output->writeln("Number of SKIPPED domains: {$numSkipped}");
        }

        return $numNotDeleted === 0 ? 0 : 1;
    }

    /**
     * @param \Acme\Entity\Domain $domain
     * @param \Acme\Editor\DomainEditor $domainEditor
     *
     * @return bool|null NULL if skipped
     */
    protected function deleteDomain(Domain $domain, DomainEditor $domainEditor)
    {
        if ($this->input->getOption('force')) {
            $this->output->writeln("# DELETING DOMAIN {$domain->getHostDisplayName()} FOR ACCOUNT {$domain->getAccount()->getName()}");
        } else {
            if (!$this->input->isInteractive()) {
                $this->output->warning("Skipping deletion of domain '{$domain->getHostDisplayName()}' (account '{$domain->getAccount()->getName()}') because it's a non interactive CLI and you didn't specify the --force option");

                return null;
            }
            $confirmQuestion = new ConfirmationQuestion(
                "Are you sure you want DELETE the domain '{$domain->getHostDisplayName()}' (account '{$domain->getAccount()->getName()}')? (y/n)",
                false
            );
            if (!$this->getHelper('question')->ask($this->input, $this->output, $confirmQuestion)) {
                $this->output->writeln('Skipped.');

                return null;
            }
        }
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $deleted = $domainEditor->delete($domain, $errors);
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }

        return $deleted;
    }
}
