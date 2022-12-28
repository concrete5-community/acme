<?php

namespace Acme\Console\Certificate;

use Acme\Certificate\Renewer;
use Acme\Certificate\RenewerOptions;
use Acme\Entity\Certificate;
use Acme\Log\ArrayLogger;
use Acme\Log\CombinedLogger;
use Acme\Log\ConsoleLogger;
use Acme\Log\StateAwareLogger;
use Concrete\Core\Console\Command;
use Concrete\Core\Mail\Service as EmailService;
use Concrete\Core\Site\Service as SiteService;
use Concrete\Core\Validator\String\EmailValidator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LogLevel;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class RefreshCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'Refresh one or all certificates, (re)generating them and/or running actions.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:certificate:refresh
    {certificate? : the certificate to be refreshed - if omitted we'll refresh all the certificates}
    {--c|check-revocation : check certificate revokation - if so, the certificate(s) will be renewed}
    {--f|force-renew : force the of certificates even if not needed}
    {--r|rerun-actions : force the execution of certificate actions even if not needed}
    {--e|email=* : the email address where the log of the operations should be sent to}
    {--i|email-if= : send an email only if the log level is at least this one}
    {--w|wholelog : specify this flag to include in the email notification the log for all the certificates, not only those with the threshhold specified in the --email-if option}
EOT
    ;

    /**
     * @var string[]
     */
    protected $emailRecipients = [];

    /**
     * @var string
     */
    protected $emailLogLevel = '';

    /**
     * The logger that contains both the consoleLog and the notificationLog.
     *
     * @var \Acme\Log\CombinedLogger
     */
    protected $combinedLog;

    /**
     * @var \Acme\Log\ConsoleLogger
     */
    protected $consoleLog;

    /**
     * @var \Acme\Log\ArrayLogger
     */
    protected $notificationLog;

    /**
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param \Acme\Certificate\Renewer $renewer
     * @param \Concrete\Core\Validator\String\EmailValidator $emailValidator
     * @param \Concrete\Core\Mail\Service $emailService
     * @param \Concrete\Core\Site\Service $siteService
     *
     * @return int
     */
    public function handle(EntityManagerInterface $em, Renewer $renewer, EmailValidator $emailValidator, EmailService $emailService, SiteService $siteService)
    {
        if ($this->parseEmailNotificationArguments($emailValidator) === false) {
            return 1;
        }
        $this->consoleLog = new ConsoleLogger($this->output);
        $this->notificationLog = new ArrayLogger();
        $this->combinedLog = new CombinedLogger();
        $this->combinedLog
            ->addLogger($this->consoleLog)
            ->addLogger($this->notificationLog)
        ;
        try {
            $this->process($em, $renewer);
        } catch (Exception $x) {
            $this->combinedLog->critical($x->getMessage());
        } catch (Throwable $x) {
            $this->combinedLog->critical($x->getMessage());
        }
        $emailNotificationSucceeded = $this->sendEmailNotification($emailService, $siteService);

        return $emailNotificationSucceeded !== true || $this->consoleLog->hasMaxLevelAtLeast(LogLevel::WARNING) ? 1 : 0;
    }

    /**
     * @return string[]
     */
    protected function getAllLogLevels()
    {
        return [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $help = 'Valid values for the --email-if option are (in decreasing order of problems):';
        $help .= "\n  " . implode("\n  ", $this->getAllLogLevels());
        $help .= "\n\nIf omitted, we'll always send the log of the operations to the specified email address(es).";
        $this->setHelp($help);
    }

    /**
     * @param \Concrete\Core\Validator\String\EmailValidator $emailValidator
     *
     * @return bool
     */
    protected function parseEmailNotificationArguments(EmailValidator $emailValidator)
    {
        $this->emailRecipients = [];
        $this->emailLogLevel = '';
        $recipients = $this->input->getOption('email');
        $logLevel = $this->input->getOption('email-if');
        if ($recipients === []) {
            if ($logLevel !== null) {
                $this->output->error('If you specify the --email-if argument, you must also specify the --email option');

                return false;
            }

            return true;
        }
        $logLevel = (string) $logLevel;
        if ($logLevel !== '' && !in_array($logLevel, $this->getAllLogLevels(), true)) {
            $this->output->error('The value of the --email-if option is invalid. Allowed values are: ' . implode(', ', $this->getAllLogLevels()));

            return false;
        }
        $validRecipients = [];
        $emailValidator->setTestMXRecord(true);
        foreach (array_unique($recipients) as $recipient) {
            $validityProblems = new \ArrayObject();
            if ($emailValidator->isValid($recipient, $validityProblems)) {
                $validRecipients[] = $recipient;
            }
            if ($validityProblems->count() > 0) {
                $this->output->error('Problems checking the validity of email address ' . $recipient . ":\n" . implode("\n", $validityProblems->getArrayCopy()));
            }
        }
        if ($validRecipients === []) {
            $this->output->caution('Continuing, but no notification email will be sent');

            return true;
        }

        $this->emailRecipients = $validRecipients;
        $this->emailLogLevel = $logLevel;

        return true;
    }

    /**
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param \Acme\Certificate\Renewer $renewer
     */
    protected function process(EntityManagerInterface $em, Renewer $renewer)
    {
        $id = $this->input->getArgument('certificate');
        if ($id !== null) {
            if ($id !== (string) (int) $id) {
                $this->combinedLog->error("Please specify an integer number for the 'certificate' argument");

                return;
            }
            $certificate = $em->find(Certificate::class, (int) $id);
            if ($certificate === null) {
                $this->combinedLog->error("Unable to find a certificate with ID {$id}");

                return;
            }
            if ($certificate->isDisabled()) {
                $this->combinedLog->error("The certificate with ID {$id} is disabled");

                return;
            }
            $this->processCertificate($certificate, $renewer);

            return;
        }

        $certificates = $em->getRepository(Certificate::class)->findBy(['disabled' => false]);
        if ($certificates === []) {
            $this->combinedLog->notice('No certificates found.');

            return;
        }

        foreach ($certificates as $certificate) {
            $this->processCertificate($certificate, $renewer);
        }
    }

    /**
     * @param \Acme\Entity\Certificate $certificate
     * @param \Acme\Certificate\Renewer $renewer
     */
    protected function processCertificate(Certificate $certificate, Renewer $renewer)
    {
        $domainNames = $certificate->getDomainHostDisplayNames();
        $sectionTitle = 'Processing certificate with id ' . $certificate->getID() . ' for ' . implode(', ', $domainNames);
        $this->consoleLog->section($sectionTitle);
        $options = RenewerOptions::create()
            ->setForceCertificateRenewal($this->input->getOption('force-renew'))
            ->setForceActionsExecution($this->input->getOption('rerun-actions'))
            ->setCheckRevocation($this->input->getOption('check-revocation'))
        ;
        for (;;) {
            $renewState = $renewer->nextStep($certificate, $options);
            $options = null;
            $this->consoleLog->logLogger($renewState);
            if ($this->input->getOption('wholelog') || $this->shouldNotifyLog($renewState)) {
                $this->notificationLog
                    ->section($sectionTitle)
                    ->logLogger($renewState)
                ;
            }
            $waitFor = $renewState->getNextStepAfter();
            if ($waitFor === null) {
                break;
            }
            if ($waitFor > 0) {
                $this->consoleLog->debug("Wait for {$waitFor} second(s)");
                sleep($waitFor);
            }
        }
    }

    /**
     * @param \Concrete\Core\Mail\Service $emailService
     * @param \Concrete\Core\Site\Service $siteService
     *
     * @return bool
     */
    protected function sendEmailNotification(EmailService $emailService, SiteService $siteService)
    {
        if (!$this->shouldNotifyLog($this->notificationLog)) {
            return true;
        }
        $emailService->reset();
        $site = $siteService->getSite();
        $siteName = $site ? (string) $site->getSiteName() : '';
        $textLines = [];
        foreach ($this->notificationLog->getEntries() as $entry) {
            $textLines[] = $entry->getMessage();
        }
        $emailService->setSubject(($siteName === '' ? '' : "[{$siteName}]") . ' ACME Certificate Renewal - ' . $this->notificationLog->getMaxLevel());
        foreach ($this->emailRecipients as $recipient) {
            $emailService->to($recipient);
        }
        $emailService->setBody(implode("\n", $textLines));
        $wasThrowOnFailure = $emailService->isThrowOnFailure();
        $emailService->setIsThrowOnFailure(true);
        $sendError = null;
        $this->consoleLog->section('Sending email message');
        try {
            $emailService->sendMail();
        } catch (Exception $x) {
            $sendError = $x;
        } catch (Throwable $x) {
            $sendError = $x;
        } finally {
            $emailService->setIsThrowOnFailure($wasThrowOnFailure);
        }
        if ($sendError !== null) {
            $this->consoleLog->error($sendError->getMessage());

            return false;
        }

        $this->consoleLog->info('Email sent');

        return true;
    }

    /**
     * @param \Acme\Log\StateAwareLogger $logger
     *
     * @return bool
     */
    protected function shouldNotifyLog(StateAwareLogger $logger)
    {
        if ($this->emailRecipients === []) {
            return false;
        }
        if ($this->emailLogLevel !== '' && $logger->hasMaxLevelAtLeast($this->emailLogLevel) === false) {
            return false;
        }

        return true;
    }
}
