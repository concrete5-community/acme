<?php

namespace Acme\Console\Certificate;

use Acme\Editor\CertificateEditor;
use Acme\Entity\Account;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
use Concrete\Core\Console\Command;
use Concrete\Core\Error\ErrorList\ErrorList;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

final class AddCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'Add a new HTTPS Certificate.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:certificate:add
    {domains* : The list of domains to be included in the certificate - use their IDs or host names}
    {--a|account= : The name or ID of the ACME Account - if not specified we'll use the default one}
    {--p|primary= : The primary domain for the certificate; if not specified, the first domain in the 'domains' argument will be used - use its ID or host names}
    {--k|private-key-size= : The size of the certificate private key to be created, in bits}
EOT
    ;

    public function handle(CertificateEditor $certificateEditor, EntityManagerInterface $em, Finder $finder)
    {
        $accountIDOrName = $this->input->getOption('account');
        if ($accountIDOrName === null) {
            $account = $em->getRepository(Account::class)->findOneBy(['isDefault' => true]);
            if ($account === null) {
                $this->output->error('No default ACME account found.');

                return 1;
            }
        } else {
            try {
                $account = $finder->findAccount($accountIDOrName);
            } catch (EntityNotFoundException $x) {
                $this->output->error($x->getMessage());

                return 1;
            }
        }
        $data = [
            'domains' => $this->input->getArgument('domains'),
            'primaryDomain' => $this->input->getOption('primary'),
            'privateKeyBits' => $this->input->getOption('private-key-size'),
        ];
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $certificate = $certificateEditor->create(
            $account,
            $data,
            $errors
        );
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }
        if ($certificate === null) {
            return 1;
        }
        $this->output->writeln("The certificate has been added (it has been assigned the ID {$certificate->getID()})");

        return 0;
    }
}
