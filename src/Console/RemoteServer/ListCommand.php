<?php

namespace Acme\Console\RemoteServer;

use Acme\Crypto\KeyPair;
use Acme\Entity\RemoteServer;
use Acme\Exception\EntityNotFoundException;
use Acme\Filesystem\DriverManager;
use Acme\Filesystem\RemoteDriverInterface;
use Acme\Finder;
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
    protected $description = 'List the Remote Servers used for HTTPS certificates, or get the details about a specific remote server.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:remote:list
    {remoteServer? : show the details about a specific remote server - use either its ID or name}
EOT
    ;

    public function handle(EntityManagerInterface $em, DriverManager $filesystemDriverManager, Finder $finder)
    {
        $idOrName = $this->input->getArgument('remoteServer');
        if ($idOrName === null) {
            return $this->listRemoteServers($em, $filesystemDriverManager);
        }

        try {
            $remoteServer = $finder->findRemoteServer($idOrName);
        } catch (EntityNotFoundException $x) {
            $this->output->error($x->getMessage());

            return 1;
        }

        return $this->showRemoteServerDetails($remoteServer, $filesystemDriverManager);
    }

    /**
     * @return int
     */
    private function listRemoteServers(EntityManagerInterface $em, DriverManager $filesystemDriverManager)
    {
        $table = new Table($this->output);
        $table
            ->setHeaders([
                'ID',
                'Name',
                'Host name',
                'Driver',
                'Username',
                'Certificate Actions',
            ])
        ;
        foreach ($em->getRepository(RemoteServer::class)->findBy([], ['name' => 'ASC', 'id' => 'ASC']) as $remoteServer) {
            $table->addRow([
                $remoteServer->getID(),
                $remoteServer->getName(),
                $remoteServer->getHostname(),
                $filesystemDriverManager->getDriverName($remoteServer->getDriverHandle()),
                $remoteServer->getUsername(),
                $remoteServer->getCertificateActions()->count(),
            ]);
        }
        $table->render();

        return 0;
    }

    /**
     * @return int
     */
    private function showRemoteServerDetails(RemoteServer $remoteServer, DriverManager $filesystemDriverManager)
    {
        $drivers = $filesystemDriverManager->getDrivers(null, RemoteDriverInterface::class);
        $driverInfo = $drivers[$remoteServer->getDriverHandle()];
        $this->output->writeln([
            'ID                 : ' . $remoteServer->getID(),
            'Name               : ' . $remoteServer->getName(),
            'Created on         : ' . $remoteServer->getCreatedOn()->format('c'),
            'Port               : ' . ($remoteServer->getPort() ?: '(default)'),
            'Connection timeout : ' . ($remoteServer->getConnectionTimeout() ?: '(default)'),
            'Driver             : ' . $driverInfo['name'],
            'Driver is available: ' . ($driverInfo['available'] ? 'Yes' : 'No'),
        ]);
        if ($driverInfo['loginFlags'] & RemoteDriverInterface::LOGINFLAG_USERNAME) {
            $this->output->writeln([
                'Username           : ' . $remoteServer->getUsername(),
            ]);
        }
        if ($driverInfo['loginFlags'] & RemoteDriverInterface::LOGINFLAG_PRIVATEKEY) {
            $keyPair = KeyPair::fromPrivateKeyString($remoteServer->getPrivateKey());
            $this->output->writeln([
                'Private key size   : ' . ($keyPair === null ? '' : "{$keyPair->getPrivateKeySize()} bits"),
            ]);
        }
        if ($driverInfo['loginFlags'] & RemoteDriverInterface::LOGINFLAG_SSHAGENT) {
            $this->output->writeln([
                'SSH Agent socket   : ' . $remoteServer->getSshAgentSocket(),
            ]);
        }

        return 0;
    }
}
