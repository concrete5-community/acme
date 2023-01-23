<?php

namespace Acme\Console\RemoteServer;

use Acme\Editor\RemoteServerEditor;
use Acme\Exception\EntityNotFoundException;
use Acme\Exception\FilesystemException;
use Acme\Filesystem\DriverManager as FilesystemDriverManager;
use Acme\Finder;
use Concrete\Core\Error\ErrorList\ErrorList;

defined('C5_EXECUTE') or die('Access Denied.');

final class EditCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'Edit an existing remote server used for HTTPS certificates.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:remote:edit
    {remote-server : The mnemonic name, or the ID, of an existing ACME server}
    {--r|name= : Change the mnemonic name used to identify the remote server}
    {--i|hostname= : Change the host name / IP address of the remote server}
    {--d|driver= : Change the handle of the filesysem driver to be used to connect to the remote server}
    {--u|username= : Change the username to be used to connect to remote server}
    {--p|password= : Change the password to be used to connect to remote server}
    {--k|private-key= : Change the path to a local file to be used to connect to remote server}
    {--a|ssh-agent-socket= : Change the socket name of the SSH agent to be used to connect to remote server}
    {--o|connection-port= : Change the TCP port to be used to connect to the remote server}
    {--t|connection-timeout= : Change the timeout (in seconds) for the connection to the remote server}
EOT
    ;

    public function handle(RemoteServerEditor $remoteServerEditor, Finder $finder, FilesystemDriverManager $filesystemDriverManager)
    {
        try {
            $remoteServer = $finder->findRemoteServer($this->input->getArgument('remote-server'));
        } catch (EntityNotFoundException $x) {
            $this->output->error($x->getMessage());

            return 1;
        }
        $privateKey = $remoteServer->getPrivateKey();
        $privateKeyFile = (string) $this->input->getOption('private-key');
        if ($privateKeyFile !== '') {
            $localDriver = $filesystemDriverManager->getLocalDriver();
            if (!$localDriver->isFile($privateKeyFile)) {
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
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $updated = $remoteServerEditor->edit(
            $remoteServer,
            [
                // '', 'sshAgentSocket'
                'name' => $this->getOptionValue('name', $remoteServer->getName()),
                'hostname' => $this->getOptionValue('hostname', $remoteServer->getHostname()),
                'driver' => $this->getOptionValue('driver', $remoteServer->getDriverHandle()),
                'port' => $this->getOptionValue('connection-port', $remoteServer->getPort()),
                'connectionTimeout' => $this->getOptionValue('connection-timeout', $remoteServer->getConnectionTimeout()),
                'username' => $this->getOptionValue('username', $remoteServer->getUsername()),
                'password' => $this->getOptionValue('password', $remoteServer->getPassword()),
                'privateKey' => $privateKey,
                'sshAgentSocket' => $this->getOptionValue('ssh-agent-socket', $remoteServer->getSshAgentSocket()),
            ],
            $errors
        );
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }
        if (!$updated) {
            return 1;
        }

        $this->output->writeln('The remote server has been updated.');

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

    private function getOptionValue($optionName, $remoteServerValue)
    {
        $result = $this->input->getOption($optionName);

        return $result === null ? $remoteServerValue : $result;
    }
}
