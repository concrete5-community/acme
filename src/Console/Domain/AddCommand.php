<?php

namespace Acme\Console\Domain;

use Acme\Editor\DomainEditor;
use Acme\Entity\Account;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
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
    protected $description = 'Add a new domain for HTTPS certificates.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:domain:add
    {hostname : The domain name (wildcards and international characters are supported)}
    {challengetype : The identifier of the authorization method}
    {--a|account= : The name or ID of the ACME Account - if not specified we'll use the default one}
EOT
    ;

    public function handle(DomainEditor $domainEditor, EntityManagerInterface $em, Finder $finder)
    {
        $accountIDOrName = $this->input->getOption('account');
        if ($accountIDOrName === null) {
            $account = $em->getRepository(Account::class)->findOneBy(['isDefault' => true]);
            if ($account === null) {
                $this->output->error("There's no default ACME account");

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
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $domain = $domainEditor->create(
            $account,
            [
                'hostname' => $this->input->getArgument('hostname'),
                'challengetype' => $this->input->getArgument('challengetype'),
            ] + $this->getChallengeTypeOptionsFromInput(),
            $errors
        );
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }
        if ($domain === null) {
            return 1;
        }
        $this->output->writeln("The domain has been added (it has been assigned the ID {$domain->getID()})");

        return 0;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {
        $this->setAllChallengeTypeOptions();
        $this->setHelp($this->describeChallengeTypeOptions());
    }
}
