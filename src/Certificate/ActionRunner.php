<?php

namespace Acme\Certificate;

use Acme\Entity\CertificateAction;
use Acme\Exception\Exception;
use Acme\Exception\FilesystemException;
use Acme\Filesystem\DriverInterface;
use Acme\Filesystem\DriverManager;
use Acme\Filesystem\ExecutableDriverInterface;
use Psr\Log\LoggerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class ActionRunner
{
    /**
     * @var \Acme\Filesystem\DriverManager
     */
    protected $filesystemDriverManager;

    /**
     * @param \Concrete\Core\System\Mutex\MutexInterface $mutex
     * @param DriverManager $filesystemDriverManager
     */
    public function __construct(DriverManager $filesystemDriverManager)
    {
        $this->filesystemDriverManager = $filesystemDriverManager;
    }

    /**
     * Execute an CertificateAction.
     *
     * @param \Acme\Entity\CertificateAction $action
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function runAction(CertificateAction $action, LoggerInterface $logger)
    {
        $certificate = $action->getCertificate();
        $certificateInfo = $certificate->getCertificateInfo();
        try {
            if ($action->getRemoteServer() === null) {
                $driver = $this->filesystemDriverManager->getLocalDriver();
            } else {
                $driver = $this->filesystemDriverManager->getRemoteDriver($action->getRemoteServer());
                $driver->checkConnection();
            }
        } catch (Exception $x) {
            $logger->critical(t('Failed to get the driver to be used to connect to the server: %s', $x->getMessage()));

            return;
        } catch (FilesystemException $x) {
            $logger->critical(t('The driver failed to connect to the server: %s', $x->getMessage()));

            return;
        }

        $somethingDone = false;

        if ($action->isSavePrivateKey() && $action->getSavePrivateKeyTo() !== '') {
            $this->saveFile($action, $driver, $certificate->getPrivateKey(), $action->getSavePrivateKeyTo(), $logger);
            $somethingDone = true;
        }
        if ($action->isSaveCertificate() && $action->getSaveCertificateTo() !== '') {
            $this->saveFile($action, $driver, $certificateInfo->getCertificate(), $action->getSaveCertificateTo(), $logger);
            $somethingDone = true;
        }
        if ($action->isSaveIssuerCertificate() && $action->getSaveIssuerCertificateTo() !== '') {
            $this->saveFile($action, $driver, $certificateInfo->getIssuerCertificate(), $action->getSaveIssuerCertificateTo(), $logger);
            $somethingDone = true;
        }
        if ($action->isSaveCertificateWithIssuer() && $action->getSaveCertificateWithIssuerTo() !== '') {
            $this->saveFile($action, $driver, $certificateInfo->getCertificateWithIssuer(), $action->getSaveCertificateWithIssuerTo(), $logger);
            $somethingDone = true;
        }
        if ($action->isExecuteCommand() && $action->getCommandToExecute() !== '') {
            $this->executeCommand($action, $driver, $action->getCommandToExecute(), $logger);
            $somethingDone = true;
        }
        if ($somethingDone === false) {
            $logger->info(t("The action doesn't perform anything"));
        }
    }

    /**
     * @param \Acme\Entity\CertificateAction $action
     * @param \Acme\Filesystem\DriverInterface $driver
     * @param string $contents
     * @param string $path
     * @param \Psr\Log\LoggerInterface $logger
     */
    protected function saveFile(CertificateAction $action, DriverInterface $driver, $contents, $path, LoggerInterface $logger)
    {
        try {
            $driver->setFileContents($path, $contents);
            $logger->info(t('The file %s has been updated', $path));
        } catch (FilesystemException $x) {
            $logger->critical(t('Failed to save the file %1$s: %2$s', $path, $x->getMessage()));
        }
    }

    /**
     * @param \Acme\Entity\CertificateAction $action
     * @param \Acme\Filesystem\DriverInterface $driver
     * @param string $command
     * @param \Psr\Log\LoggerInterface $logger
     */
    protected function executeCommand(CertificateAction $action, DriverInterface $driver, $command, LoggerInterface $logger)
    {
        if (!$driver instanceof ExecutableDriverInterface) {
            $logger->error(t("The command can't be executed since the file system driver doesn't support executing commands"));

            return;
        }
        $output = '';
        try {
            $rc = $driver->executeCommand($command, $output);
            if ($rc === 0) {
                return $logger->info(t("The command completed succesfully. Its output is:\n%s", $output));
            }

            return $logger->critical(t("The command returned a non-zero value (%1\$s). Its output is:\n%2\$s", $rc, $output));
        } catch (FilesystemException $x) {
            return $logger->critical(t('Failed to execute the command: %s', $x->getMessage()));
        }
    }
}
