<?php

namespace Acme\Console\Server;

use Acme\Entity\Server;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
use Acme\Protocol\Version;
use Concrete\Core\Console\Command;
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
    protected $description = 'List the ACME Servers, or get the details about a specific ACME Server.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:server:list
    {server? : show the details about a specific server - use either its ID or name}
EOT
    ;

    public function handle(EntityManagerInterface $em, Version $protocolVersion, Finder $finder)
    {
        $idOrName = $this->input->getArgument('server');
        if ($idOrName === null) {
            return $this->listServers($em, $protocolVersion);
        }
        try {
            $server = $finder->findServer($idOrName);
        } catch (EntityNotFoundException $x) {
            $this->output->error($x->getMessage());

            return 1;
        }

        return $this->showServerDetails($server, $protocolVersion);
    }

    /**
     * @return int
     */
    private function listServers(EntityManagerInterface $em, Version $protocolVersion)
    {
        $table = new Table($this->output);
        $table
            ->setHeaders([
                'Default',
                'ID',
                'Name',
                'Protocol',
                'Accounts',
                'Entry point',
            ])
        ;
        foreach ($em->getRepository(Server::class)->findBy([], ['name' => 'ASC', 'id' => 'ASC']) as $server) {
            $table->addRow([
                $server->isDefault() ? '*' : '',
                $server->getID(),
                $server->getName(),
                $protocolVersion->getProtocolVersionName($server->getProtocolVersion()),
                $server->getAccounts()->count(),
                $server->getDirectoryUrl(),
            ]);
        }
        $table->render();

        return 0;
    }

    /**
     * @return int
     */
    private function showServerDetails(Server $server, Version $protocolVersion)
    {
        $this->output->writeln([
            'ID                 : ' . $server->getID(),
            'Default            : ' . ($server->isDefault() ? 'Yes' : 'No'),
            'Name               : ' . $server->getName(),
            'Created on         : ' . $server->getCreatedOn()->format('c'),
            'Entry point        : ' . $server->getDirectoryUrl(),
            'Protocol           : ' . $protocolVersion->getProtocolVersionName($server->getProtocolVersion()),
            'Terms of service   : ' . $server->getTermsOfServiceUrl(),
            'Website            : ' . $server->getWebsite(),
            'Unsafe connections : ' . ($server->isAllowUnsafeConnections() ? 'Yes' : 'No'),
            'Authorization ports: ' . implode(', ', $server->getAuthorizationPorts()),
            'Registered accounts: ' . $server->getAccounts()->count(),
        ]);

        return 0;
    }
}
