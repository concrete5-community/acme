<?php

namespace Acme\Console\Server;

use Acme\Editor\ServerEditor;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
use Concrete\Core\Console\Command;
use Concrete\Core\Error\ErrorList\ErrorList;

defined('C5_EXECUTE') or die('Access Denied.');

class EditCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'Edit an existing ACME Server.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:server:edit
    {server : The mnemonic name, or the ID, of an existing ACME server}
    {--m|name= : Change the name of the ACME server}
    {--r|directory-url= : Change the directory URL of the ACME server (WARNING! This should NOT be done if the ACME server has account associated to it)}
    {--d|default : Set the ACME server as the default one}
    {--u|unsafe-connections= : Set to 1 to allow unsafe connections (useful when developing/testing ACME servers), to 0 otherwise}
    {--p|authorization-ports= : Comma-separated list of ports used when authorizing domains via HTTP}
EOT
    ;

    public function handle(ServerEditor $serverEditor, Finder $finder)
    {
        try {
            $server = $finder->findServer($this->input->getArgument('server'));
        } catch (EntityNotFoundException $x) {
            $this->output->error($x->getMessage());

            return 1;
        }
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $updated = $serverEditor->edit(
            $server,
            [
                'name' => $this->getOptionValue('name', $server->getName()),
                'directoryUrl' => $this->getOptionValue('directory-url', $server->getDirectoryUrl()),
                'default' => $this->getOptionValue('default', $server->isDefault()),
                'authorizationPorts' => $this->getOptionValue('authorization-ports', $server->getAuthorizationPorts()),
                'allowUnsafeConnections' => $this->getOptionValue('unsafe-connections', $server->isAllowUnsafeConnections()),
            ],
            $errors
        );
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }
        if (!$updated) {
            return 1;
        }

        $this->output->writeln('The server has been updated.');

        return 0;
    }

    protected function getOptionValue($optionName, $serverValue)
    {
        $result = $this->input->getOption($optionName);

        return $result === null ? $serverValue : $result;
    }
}
