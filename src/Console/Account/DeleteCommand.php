<?php

namespace Acme\Console\Account;

use Acme\Editor\AccountEditor;
use Acme\Entity\Account;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
use Concrete\Core\Console\Command;
use Concrete\Core\Error\ErrorList\ErrorList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

defined('C5_EXECUTE') or die('Access Denied.');

class DeleteCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'Delete existing ACME Accounts.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:account:delete
    {account? : The mnemonic name, or the ID, of an existing ACME account}
    {--a|all : Delete ALL the ACME accounts}
    {--s|server= : The mnemonic name, or the ID, of the ACME Server - used with the --all option}
    {--f|force : Don't ask confirmation}
EOT
    ;

    public function handle(AccountEditor $accountEditor, EntityManagerInterface $em, Finder $finder)
    {
        $nameOrID = $this->input->getArgument('account');
        $all = $this->input->getOption('all');
        if ($nameOrID !== null) {
            if ($all === true) {
                $this->output->error("If you specify the 'account' argument, you can't specify the --all option");

                return 1;
            }
            try {
                $account = $finder->findAccount($nameOrID);
            } catch (EntityNotFoundException $x) {
                $this->output->error($x->getMessage());

                return 1;
            }

            return $this->deleteAccount($account, $accountEditor) ? 1 : 0;
        }
        if ($all === false) {
            $this->output->error("Neither the 'account' argument, nor the '--all' option has been specified");

            return 1;
        }
        $serverNameOrID = $this->input->getOption('server');
        if ($serverNameOrID === null) {
            $server = null;
        } else {
            try {
                $server = $finder->findServer($serverNameOrID);
            } catch (EntityNotFoundException $x) {
                $this->output->error($x->getMessage());

                return 1;
            }
        }
        $qb = $em->createQueryBuilder();
        $qb
            ->from(Account::class, 'a')
            ->select('a');

        if ($server !== null) {
            $qb->andWhere($qb->expr()->eq('a.server', ':server'))->setParameter('server', $server);
        }
        $numDeleted = 0;
        $numNotDeleted = 0;
        $numSkipped = 0;
        foreach ($qb->getQuery()->execute() as $account) {
            $deleted = $this->deleteAccount($account, $accountEditor);
            if ($deleted === true) {
                ++$numDeleted;
            } elseif ($deleted === false) {
                ++$numDeleted;
            } else {
                ++$numSkipped;
            }
        }

        $this->output->writeln("Number of DELETED accounts: {$numDeleted}");
        if ($numNotDeleted !== 0) {
            $this->output->error("Number of NOT deleted accounts: {$numNotDeleted}");
        }
        if ($numSkipped !== 0) {
            $this->output->writeln("Number of SKIPPED accounts: {$numSkipped}");
        }

        return $numNotDeleted === 0 ? 0 : 1;
    }

    protected function deleteAccount(Account $account, AccountEditor $accountEditor)
    {
        if ($this->input->getOption('force')) {
            $this->output->writeln("# DELETING ACME ACCOUNT {$account->getName()}");
        } else {
            if (!$this->input->isInteractive()) {
                $this->output->warning("Skipping deletion of ACME account '{$account->getName()}' (server '{$account->getServer()->getName()}') because it's a non interactive CLI and you didn't specify the --force option");

                return null;
            }
            $confirmQuestion = new ConfirmationQuestion(
                "Are you sure you want DELETE the ACME account named '{$account->getName()}' (server '{$account->getServer()->getName()}')? (y/n)",
                false
            );
            if (!$this->getHelper('question')->ask($this->input, $this->output, $confirmQuestion)) {
                $this->output->writeln('Skipped.');

                return null;
            }
        }
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $deleted = $accountEditor->delete($account, $errors);
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }

        return $deleted;
    }
}
