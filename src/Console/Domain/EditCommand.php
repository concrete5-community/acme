<?php

namespace Acme\Console\Domain;

use Acme\Editor\DomainEditor;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
use Concrete\Core\Error\ErrorList\ErrorList;

defined('C5_EXECUTE') or die('Access Denied.');

final class EditCommand extends Command
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$description
     */
    protected $description = 'Edit an existing domain for HTTPS certificates.';

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Console\Command::$signature
     */
    protected $signature = <<<'EOT'
acme:domain:edit
    {domain : The host name, or the ID, of an existing domain}
    {--m|hostname= : Change the host name of the domain (wildcards and international characters are supported)}
    {--c|challengetype= : Change the authorization method}
EOT
    ;

    public function handle(DomainEditor $domainEditor, Finder $finder)
    {
        try {
            $domain = $finder->findDomain($this->input->getArgument('domain'));
        } catch (EntityNotFoundException $x) {
            $this->output->error($x->getMessage());

            return 1;
        }
        $domainChallengeTypeHandle = $domain->getChallengeTypeHandle();
        $domainChallengeTypeOptions = in_array($this->input->getOption('challengetype'), [null, $domainChallengeTypeHandle], true) ? $domain->getChallengeTypeConfiguration() : [];
        $challengeTypeOptions = $this->getChallengeTypeOptionsFromInput() + $domainChallengeTypeOptions;
        $errors = $this->getApplication()->getConcrete5()->make(ErrorList::class);
        $updated = $domainEditor->edit(
            $domain,
            [
                'hostname' => $this->getOptionValue('hostname', $domain->getHostDisplayName()),
                'challengetype' => $this->getOptionValue('challengetype', $domainChallengeTypeHandle),
            ] + $challengeTypeOptions,
            $errors
        );
        if ($errors->has()) {
            $this->output->error($errors->toText());
        }
        if (!$updated) {
            return 1;
        }

        $this->output->writeln('The domain has been updated.');

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

    private function getOptionValue($optionName, $domainValue)
    {
        $result = $this->input->getOption($optionName);

        return $result === null ? $domainValue : $result;
    }
}
