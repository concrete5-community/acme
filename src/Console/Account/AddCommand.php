<?php

namespace Acme\Console\Account;

use Acme\Editor\AccountEditor;
use Acme\Entity\Server;
use Acme\Exception\EntityNotFoundException;
use Acme\Exception\FilesystemException;
use Acme\Filesystem\DriverManager as FilesystemDriverManager;
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
    protected $description = 'Add a new ACME Account.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:account:add
    {name : The mnemonic name used to identify the ACME account}
    {email : The email address to be associated to the ACME account}
    {--s|server= : The name or ID of the ACME Server - if not specified we'll use the default one}
    {--d|default : Set the ACME account as the default one}
    {--t|accepted-terms-of-service : Specify this flag if you accept the Terms of Service of the ACME Server}
    {--e|existing-user-pk= : In case you are creating an ACME account previously registered at the ACME Server, the path to a file containing the account private key}
    {--p|private-key-size= : The size of the account private key to be created, in bits}
EOT
    ;

    public function handle(AccountEditor $accountEditor, EntityManagerInterface $em, Finder $finder, FilesystemDriverManager $filesystemDriverManager)
    {
        $serverIDOrName = $this->input->getOption('server');
        if ($serverIDOrName === null) {
            $server = $em->getRepository(Server::class)->findOneBy(['isDefault' => true]);
            if ($server === null) {
                $this->output->error('No default ACME server found.');

                return 1;
            }
        } else {
            try {
                $server = $finder->findServer($serverIDOrName);
            } catch (EntityNotFoundException $x) {
                $this->output->error($x->getMessage());

                return 1;
            }
        }
        $data = [
            'name' => $this->input->getArgument('name'),
            'email' => $this->input->getArgument('email'),
            'default' => $this->input->getOption('default'),
            'acceptedTermsOfService' => $this->input->getOption('accepted-terms-of-service'),
        ];
        $pk = $this->input->getOption('existing-user-pk');
        if ($pk) {
            $localDriver = $filesystemDriverManager->getLocalDriver();
            if (!$localDriver->isFile($pk)) {
                $this->output->error("Unable to find the private key file {$pk}");

                return 1;
            }
            try {
                $pkContents = $localDriver->getFileContents($pk);
            } catch (FilesystemException $x) {
                $this->output->error($x->getMessage());

                return 1;
            }
            $data['useExisting'] = true;
            $data['privateKey'] = $pkContents;
        } else {
            $data['privateKeyBits'] = $this->input->getOption('private-key-size');
        }
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $account = $accountEditor->create(
            $server,
            $data,
            $errors
        );
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }
        if ($account === null) {
            return 1;
        }
        $this->output->writeln("The account has been added (it has been assigned the ID {$account->getID()})");

        return 0;
    }
}
