<?php

namespace Acme\Console\Account;

use Acme\Editor\AccountEditor;
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
    protected $description = 'Edit an existing ACME account.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:account:edit
    {account : The mnemonic name, or the ID, of an existing ACME account}
    {--m|name= : Change the name of the ACME account}
    {--d|default : Set the ACME account as the default one}
EOT
    ;

    public function handle(AccountEditor $accountEditor, Finder $finder)
    {
        try {
            $account = $finder->findAccount($this->input->getArgument('account'));
        } catch (EntityNotFoundException $x) {
            $this->output->error($x->getMessage());

            return 1;
        }
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $updated = $accountEditor->edit(
            $account,
            [
                'name' => $this->getOptionValue('name', $account->getName()),
                'default' => $this->getOptionValue('default', $account->isDefault()),
            ],
            $errors
        );
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }
        if (!$updated) {
            return 1;
        }

        $this->output->writeln('The account has been updated.');

        return 0;
    }

    protected function getOptionValue($optionName, $accountValue)
    {
        $result = $this->input->getOption($optionName);

        return $result === null ? $accountValue : $result;
    }
}
