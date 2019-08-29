<?php

namespace Acme\Console\RemoteServer;

use Acme\Editor\RemoteServerEditor;
use Acme\Entity\RemoteServer;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
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
    protected $description = 'Delete existing remote servers used for HTTPS certificates.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:remote:delete
    {remote-server? : The mnemonic name, or the ID, of an existing remote server}
    {--a|all : Delete ALL the remote servers}
    {--f|force : Don't ask confirmation}
EOT
    ;

    public function handle(RemoteServerEditor $remoteServerEditor, EntityManagerInterface $em, Finder $finder)
    {
        $nameOrID = $this->input->getArgument('remote-server');
        $all = $this->input->getOption('all');
        if ($nameOrID !== null) {
            if ($all === true) {
                $this->output->error("If you specify the 'remote-server' argument, you can't specify the --all option");

                return 1;
            }
            try {
                $remoteServer = $finder->findRemoteServer($nameOrID);
            } catch (EntityNotFoundException $x) {
                $this->output->error($x->getMessage());

                return 1;
            }

            return $this->deleteRemoteServer($remoteServer, $remoteServerEditor) ? 1 : 0;
        }
        if ($all === false) {
            $this->output->error("Neither the 'remote-server' argument, nor the '--all' option has been specified");

            return 1;
        }
        $numDeleted = 0;
        $numNotDeleted = 0;
        $numSkipped = 0;
        foreach ($em->getRepository(RemoteServer::class)->findAll() as $remoteServer) {
            $deleted = $this->deleteRemoteServer($remoteServer, $remoteServerEditor);
            if ($deleted === true) {
                ++$numDeleted;
            } elseif ($deleted === false) {
                ++$numDeleted;
            } else {
                ++$numSkipped;
            }
        }

        $this->output->writeln("Number of DELETED remote servers: {$numDeleted}");
        if ($numNotDeleted !== 0) {
            $this->output->error("Number of NOT deleted remote servers: {$numNotDeleted}");
        }
        if ($numSkipped !== 0) {
            $this->output->writeln("Number of SKIPPED remote servers: {$numSkipped}");
        }

        return $numNotDeleted === 0 ? 0 : 1;
    }

    protected function deleteRemoteServer(RemoteServer $remoteServer, RemoteServerEditor $remoteServerEditor)
    {
        if ($this->input->getOption('force')) {
            $this->output->writeln("# DELETING REMOTE SERVER {$remoteServer->getName()}");
        } else {
            if (!$this->input->isInteractive()) {
                $this->output->warning("Skipping deletion of remote server {$remoteServer->getName()} because it's a non interactive CLI and you didn't specify the --force option");

                return null;
            }
            $confirmQuestion = new ConfirmationQuestion(
                "Are you sure you want DELETE the remote server named '{$remoteServer->getName()}'? (y/n)",
                false
            );
            if (!$this->getHelper('question')->ask($this->input, $this->output, $confirmQuestion)) {
                $this->output->writeln('Skipped.');

                return null;
            }
        }
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $deleted = $remoteServerEditor->delete($remoteServer, $errors);
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }

        return $deleted;
    }
}
