<?php

namespace Acme\Console\Certificate;

use Acme\Editor\CertificateEditor;
use Acme\Entity\Certificate;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
use Concrete\Core\Console\Command;
use Concrete\Core\Error\ErrorList\ErrorList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

defined('C5_EXECUTE') or die('Access Denied.');

class DeleteCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'Delete existing Certificate instances.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:certificate:delete
    {certificate? : the ID of the certificate to be deleted}
    {--a|all : Delete ALL the certificates}
    {--s|server= : The mnemonic name, or the ID, of the ACME Server - used with the --all option}
    {--c|account= : The mnemonic name, or the ID, of the ACME Account - used with the --all option}
    {--f|force : Don't ask confirmation}
EOT
    ;

    public function handle(CertificateEditor $certificateEditor, EntityManagerInterface $em, Finder $finder)
    {
        $id = $this->input->getArgument('certificate');
        $all = $this->input->getOption('all');
        $serverNameOrID = $this->input->getOption('server');
        $accountNameOrID = $this->input->getOption('account');
        if ($id !== null) {
            if ($all === true || $serverNameOrID !== null || $accountNameOrID !== null) {
                $this->output->error("If you specify the 'certificate' argument, you can't specify the --all / --server / --account options");

                return 1;
            }
            if ($id !== (string) (int) $id) {
                $this->output->error("Please specify an integer number for the 'certificate' argument");

                return 1;
            }
            $certificate = $em->find(Certificate::class, (int) $id);
            if ($certificate === null) {
                $this->output->error("Unable to find a certificate with ID {$id}");

                return 1;
            }

            return $this->deleteCertificate($certificate, $certificateEditor) ? 1 : 0;
        }
        if ($all === false) {
            $this->output->error("Neither the 'certificate' argument, nor the '--all' option has been specified");

            return 1;
        }
        if ($accountNameOrID !== null) {
            if ($serverNameOrID !== null) {
                $this->output->error("Please don't specify both the --server and the --account options");

                return 1;
            }
            try {
                $account = $finder->findAccount($accountNameOrID);
            } catch (EntityNotFoundException $x) {
                $this->output->error($x->getMessage());

                return 1;
            }
            $server = $account->getServer();
        } elseif ($serverNameOrID !== null) {
            try {
                $server = $finder->findServer($serverNameOrID);
            } catch (EntityNotFoundException $x) {
                $this->output->error($x->getMessage());

                return 1;
            }
            $account = null;
        } else {
            $server = null;
            $account = null;
        }

        $qb = $em->createQueryBuilder();
        $qb
            ->from(Certificate::class, 'c')
            ->select('c')
        ;
        if ($account !== null) {
            $qb->andWhere($qb->expr()->eq('c.account', ':account'))->setParameter('account', $account);
        } elseif ($server !== null) {
            $qb
                ->innerJoin('c.account', 'a')
                ->andWhere($qb->expr()->eq('a.server', ':server'))->setParameter('server', $server)
            ;
        }
        $numDeleted = 0;
        $numNotDeleted = 0;
        $numSkipped = 0;
        foreach ($qb->getQuery()->execute() as $certificate) {
            $deleted = $this->deleteCertificate($certificate, $certificateEditor);
            if ($deleted === true) {
                ++$numDeleted;
            } elseif ($deleted === false) {
                ++$numDeleted;
            } else {
                ++$numSkipped;
            }
        }

        $this->output->writeln("Number of DELETED certificates: {$numDeleted}");
        if ($numNotDeleted !== 0) {
            $this->output->error("Number of NOT deleted certificates: {$numNotDeleted}");
        }
        if ($numSkipped !== 0) {
            $this->output->writeln("Number of SKIPPED certificates: {$numSkipped}");
        }

        return $numNotDeleted === 0 ? 0 : 1;
    }

    /**
     * @param \Acme\Entity\Certificate $certificate
     * @param \Acme\Editor\CertificateEditor $certificateEditor
     *
     * @return bool|null NULL if skipped
     */
    protected function deleteCertificate(Certificate $certificate, CertificateEditor $certificateEditor)
    {
        $domainNames = [];
        foreach ($certificate->getDomains() as $certificateDomain) {
            $domainNames[] = $certificateDomain->getDomain()->getHostDisplayName();
        }
        $domainNames = implode(', ', $domainNames);
        if ($this->input->getOption('force')) {
            $this->output->writeln("# DELETING CERTIFICATE FOR {$domainNames} (ID: {$certificate->getID()}, account: {$certificate->getAccount()->getName()})");
        } else {
            if (!$this->input->isInteractive()) {
                $this->output->warning("Skipping deletion of certificate for {$domainNames} (ID: {$certificate->getID()}, account: {$certificate->getAccount()->getName()}) because it's a non interactive CLI and you didn't specify the --force option");

                return null;
            }
            $confirmQuestion = new ConfirmationQuestion(
                "Are you sure you want DELETE the certificate for {$domainNames} (ID: {$certificate->getID()}, account: {$certificate->getAccount()->getName()})? (y/n)",
                false
            );
            if (!$this->getHelper('question')->ask($this->input, $this->output, $confirmQuestion)) {
                $this->output->writeln('Skipped.');

                return null;
            }
        }
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $deleted = $certificateEditor->delete($certificate, $errors);
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }

        return $deleted;
    }
}
