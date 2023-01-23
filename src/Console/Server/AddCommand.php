<?php

namespace Acme\Console\Server;

use Acme\Editor\ServerEditor;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Console\Command;
use Concrete\Core\Error\ErrorList\ErrorList;

defined('C5_EXECUTE') or die('Access Denied.');

final class AddCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'Add a new ACME Server.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:server:add
    {name : The mnemonic name used to identify the ACME server}
    {directory-url : The URL of the directory of the ACME server}
    {--d|default : Set the ACME server as the default one}
    {--p|authorization-ports= : Comma-separated list of ports used when authorizing domains via HTTP}
    {--u|unsafe-connections : Allow unsafe connections (useful when developing/testing ACME servers)}
EOT
    ;

    public function handle(ServerEditor $serverEditor, Repository $config)
    {
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $server = $serverEditor->create(
            [
                'name' => $this->input->getArgument('name'),
                'directoryUrl' => $this->input->getArgument('directory-url'),
                'default' => $this->input->getOption('default'),
                'authorizationPorts' => $this->getOptionWithDefaultValue('authorization-ports', $config->get('acme::challenge.default_authorization_ports')),
                'allowUnsafeConnections' => $this->input->getOption('unsafe-connections'),
            ],
            $errors
        );
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }
        if ($server === null) {
            return 1;
        }
        $this->output->writeln("The server has been added (it has been assigned the ID {$server->getID()})");

        return 0;
    }

    private function getOptionWithDefaultValue($name, $defaultValue)
    {
        $optionValue = $this->input->getOption($name);

        return $optionValue === null ? $defaultValue : $optionValue;
    }
}
