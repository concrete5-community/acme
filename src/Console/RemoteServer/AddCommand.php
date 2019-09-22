<?php

namespace Acme\Console\RemoteServer;

use Acme\Editor\RemoteServerEditor;
use Acme\Exception\FilesystemException;
use Acme\Filesystem\DriverManager as FilesystemDriverManager;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Error\ErrorList\ErrorList;

defined('C5_EXECUTE') or die('Access Denied.');

class AddCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'Add a new remote server used for HTTPS certificates.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:remote:add
    {name : The mnemonic name used to identify the remote server}
    {hostname : The host name / IP address of the remote server}
    {driver : The handle of the filesysem driver to be used to connect to the remote server}
    {--u|username= : The username to be used to connect to remote server}
    {--p|password= : The password to be used to connect to remote server}
    {--k|private-key= : The path to a local file to be used to connect to remote server}
    {--a|ssh-agent-socket= : The socket name of the SSH agent to be used to connect to remote server}
    {--o|connection-port= : The TCP port to be used to connect to the remote server}
    {--t|connection-timeout= : The timeout (in seconds) for the connection to the remote server}
EOT
    ;

    public function handle(RemoteServerEditor $remoteServerEditor, Repository $config, FilesystemDriverManager $filesystemDriverManager)
    {
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $privateKey = '';
        $privateKeyFile = (string) $this->input->getOption('private-key');
        if ($privateKeyFile !== '') {
            $localDriver = $filesystemDriverManager->getLocalDriver();
            if ($localDriver->isFile($privateKeyFile)) {
                $this->output->error("Unable to find the private key file '{$privateKeyFile}'");

                return 1;
            }
            try {
                $privateKey = $localDriver->getFileContents($privateKeyFile);
            } catch (FilesystemException $x) {
                $this->output->error($x->getMessage());

                return 1;
            }
        }
        $remoteServer = $remoteServerEditor->create(
            [
                'name' => $this->input->getArgument('name'),
                'hostname' => $this->input->getArgument('hostname'),
                'driver' => $this->input->getArgument('driver'),
                'port' => $this->input->getOption('connection-port'),
                'connectionTimeout' => $this->input->getOption('connection-timeout'),
                'username' => $this->input->getOption('username'),
                'password' => $this->input->getOption('password'),
                'privateKey' => $privateKey,
                'sshAgentSocket' => $this->input->getOption('ssh-agent-socket'),
            ],
            $errors
        );
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }
        if ($remoteServer === null) {
            return 1;
        }
        $this->output->writeln("The server has been added (it has been assigned the ID {$remoteServer->getID()})");

        return 0;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this->setHelp($this->describeDriverOption());
    }
}
