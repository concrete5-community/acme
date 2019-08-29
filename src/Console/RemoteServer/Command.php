<?php

namespace Acme\Console\RemoteServer;

use Acme\Filesystem\DriverManager;
use Acme\Filesystem\RemoteDriverInterface;
use Concrete\Core\Console\Command as CoreCommand;
use Concrete\Core\Support\Facade\Application;

defined('C5_EXECUTE') or die('Access Denied.');

abstract class Command extends CoreCommand
{
    /**
     * @return string
     */
    protected function describeDriverOption()
    {
        $consoleApplication = $this->getApplication();
        if ($consoleApplication === null) {
            $app = Application::getFacadeApplication();
        } else {
            $app = $consoleApplication->getConcrete5();
        }
        $filesystemDriverManager = $app->make(DriverManager::class);

        $help = 'Valid drivers are:';
        foreach ($filesystemDriverManager->getDrivers(null, RemoteDriverInterface::class) as $handle => $info) {
            $help .= "\n - '{$handle}' for {$info['name']}";
            if (!$info['available']) {
                $help .= ' (NOT AVAILABLE)';
            }
            $loginFlags = $info['loginFlags'];
            $options = [];
            if ($loginFlags & RemoteDriverInterface::LOGINFLAG_USERNAME) {
                $options[] = '--username';
            }
            if ($loginFlags & RemoteDriverInterface::LOGINFLAG_PASSWORD) {
                $options[] = '--password';
            }
            if ($loginFlags & RemoteDriverInterface::LOGINFLAG_PRIVATEKEY) {
                $options[] = '--private-key';
            }
            if ($loginFlags & RemoteDriverInterface::LOGINFLAG_SSHAGENT) {
                $options[] = '--ssh-agent-socket';
            }
            $help .= "\n    accepted options: " . implode(' ', $options);
        }

        return $help;
    }
}
