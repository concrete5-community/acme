<?php

namespace Acme\Console\Server;

use Acme\Editor\ServerEditor;
use Acme\Entity\Server;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
use Concrete\Core\Console\Command;
use Concrete\Core\Error\ErrorList\ErrorList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

defined('C5_EXECUTE') or die('Access Denied.');

final class DeleteCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'Delete existing ACME Servers.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:server:delete
    {server? : The mnemonic name, or the ID, of an existing ACME server}
    {--a|all : Delete ALL the ACME servers}
    {--f|force : Don't ask confirmation}
EOT
    ;

    public function handle(ServerEditor $serverEditor, EntityManagerInterface $em, Finder $finder)
    {
        $nameOrID = $this->input->getArgument('server');
        $all = $this->input->getOption('all');
        if ($nameOrID !== null) {
            if ($all === true) {
                $this->output->error("If you specify the 'server' argument, you can't specify the --all option");

                return 1;
            }
            try {
                $server = $finder->findServer($nameOrID);
            } catch (EntityNotFoundException $x) {
                $this->output->error($x->getMessage());

                return 1;
            }

            return $this->deleteServer($server, $serverEditor) ? 1 : 0;
        }
        if ($all === false) {
            $this->output->error("Neither the 'server' argument, nor the '--all' option has been specified");

            return 1;
        }
        $numDeleted = 0;
        $numNotDeleted = 0;
        $numSkipped = 0;
        foreach ($em->getRepository(Server::class)->findAll() as $server) {
            $deleted = $this->deleteServer($server, $serverEditor);
            if ($deleted === true) {
                $numDeleted++;
            } elseif ($deleted === false) {
                $numNotDeleted++;
            } else {
                $numSkipped++;
            }
        }

        $this->output->writeln("Number of DELETED servers: {$numDeleted}");
        if ($numNotDeleted !== 0) {
            $this->output->error("Number of NOT deleted servers: {$numNotDeleted}");
        }
        if ($numSkipped !== 0) {
            $this->output->writeln("Number of SKIPPED servers: {$numSkipped}");
        }

        return $numNotDeleted === 0 ? 0 : 1;
    }

    /**
     * @return bool|null NULL if skipped
     */
    private function deleteServer(Server $server, ServerEditor $serverEditor)
    {
        if ($this->input->getOption('force')) {
            $this->output->writeln("# DELETING ACME SERVER {$server->getName()}");
        } else {
            if (!$this->input->isInteractive()) {
                $this->output->warning("Skipping deletion of ACME server {$server->getName()} because it's a non interactive CLI and you didn't specify the --force option");

                return null;
            }
            $confirmQuestion = new ConfirmationQuestion(
                "Are you sure you want DELETE the ACME Server named '{$server->getName()}'? (y/n)",
                false
            );
            if (!$this->getHelper('question')->ask($this->input, $this->output, $confirmQuestion)) {
                $this->output->writeln('Skipped.');

                return null;
            }
        }
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $deleted = $serverEditor->delete($server, $errors);
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }

        return $deleted;
    }
}
