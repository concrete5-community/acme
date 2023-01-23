<?php

namespace Acme;

use Acme\Protocol\NonceManager;
use Concrete\Core\Console\Application as ConsoleApplication;
use Concrete\Core\Foundation\Service\Provider;

defined('C5_EXECUTE') or die('Access Denied.');

final class ServiceProvider extends Provider
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Foundation\Service\Provider::register()
     */
    public function register()
    {
        $this->app->singleton(NonceManager::class);

        if ($this->app->bound('console')) {
            $this->setupConsoleCommands($this->app->make('console'));
        } else {
            $this->app->extend(ConsoleApplication::class, function (ConsoleApplication $consoleApplication) {
                $this->setupConsoleCommands($consoleApplication);

                return $consoleApplication;
            });
        }
    }

    private function setupConsoleCommands(ConsoleApplication $consoleApplication)
    {
        foreach ([
            Console\Account\ListCommand::class,
            Console\Account\AddCommand::class,
            Console\Account\EditCommand::class,
            Console\Account\DeleteCommand::class,
            Console\Certificate\AddCommand::class,
            Console\Certificate\DeleteCommand::class,
            Console\Certificate\EditCommand::class,
            Console\Certificate\ListCommand::class,
            Console\Certificate\RefreshCommand::class,
            Console\Domain\ListCommand::class,
            Console\Domain\AddCommand::class,
            Console\Domain\EditCommand::class,
            Console\Domain\DeleteCommand::class,
            Console\RemoteServer\ListCommand::class,
            Console\RemoteServer\AddCommand::class,
            Console\RemoteServer\EditCommand::class,
            Console\RemoteServer\DeleteCommand::class,
            Console\Server\ListCommand::class,
            Console\Server\AddCommand::class,
            Console\Server\EditCommand::class,
            Console\Server\DeleteCommand::class,
        ] as $commandClass) {
            $command = $this->app->make($commandClass);
            $consoleApplication->add($command);
        }
    }
}
