<?php

namespace Acme\Console\RemoteServer;

use Acme\Entity\RemoteServer;
use Acme\Exception\EntityNotFoundException;
use Acme\Filesystem\DriverManager;
use Acme\Filesystem\RemoteDriverInterface;
use Acme\Finder;
use Acme\Security\Crypto;
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

    public function handle(EntityManagerInterface $em, DriverManager $filesystemDriverManager, Crypto $crypto, Finder $finder)
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

        return $this->showRemoteServerDetails($remoteServer, $filesystemDriverManager, $crypto);
    }

    protected function listRemoteServers(EntityManagerInterface $em, DriverManager $filesystemDriverManager)
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

    protected function showRemoteServerDetails(RemoteServer $remoteServer, DriverManager $filesystemDriverManager, Crypto $crypto)
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
            $this->output->writeln([
                'Private key size   : ' . $crypto->getKeySize($crypto->getKeyPairFromPrivateKey($remoteServer->getPrivateKey())) . ' bits',
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
