<?php

namespace Acme\Console\Domain;

use Acme\ChallengeType\ChallengeTypeManager;
use Concrete\Core\Console\Command as CoreCommand;
use Concrete\Core\Support\Facade\Application;
use Symfony\Component\Console\Input\InputOption;

defined('C5_EXECUTE') or die('Access Denied.');

abstract class Command extends CoreCommand
{
    /**
     * @var string[]|null
     */
    protected $allChallengeTypeOptions;

    protected function setAllChallengeTypeOptions()
    {
        $consoleApplication = $this->getApplication();
        if ($consoleApplication === null) {
            $app = Application::getFacadeApplication();
        } else {
            $app = $consoleApplication->getConcrete5();
        }
        $challengeTypeManager = $app->make(ChallengeTypeManager::class);
        $allChallengeTypeOptions = [];
        foreach ($challengeTypeManager->getChallengeTypes() as $challengeType) {
            $allChallengeTypeOptions = array_merge($allChallengeTypeOptions, array_keys($challengeType->getConfigurationDefinition()));
        }
        $allChallengeTypeOptions = array_values(array_unique($allChallengeTypeOptions));
        foreach ($allChallengeTypeOptions as $challengeTypeOption) {
            $this->addOption($challengeTypeOption, null, InputOption::VALUE_REQUIRED, 'Value specific to challenge type - see Help');
        }
        $this->allChallengeTypeOptions = $allChallengeTypeOptions;
    }

    /**
     * @return array
     */
    protected function getChallengeTypeOptionsFromInput()
    {
        $result = [];
        foreach ($this->allChallengeTypeOptions as $challengeTypeOption) {
            $value = $this->input->getOption($challengeTypeOption);
            if ($value !== null) {
                $result[$challengeTypeOption] = $value;
            }
        }

        return $result;
    }

    /**
     * @return string
     */
    protected function describeChallengeTypeOptions()
    {
        $consoleApplication = $this->getApplication();
        if ($consoleApplication === null) {
            $app = Application::getFacadeApplication();
        } else {
            $app = $consoleApplication->getConcrete5();
        }
        $challengeTypeManager = $app->make(ChallengeTypeManager::class);

        $help = 'Valid challenge types are:';
        foreach ($challengeTypeManager->getChallengeTypes() as $challengeType) {
            $help .= "\n\n - '{$challengeType->getHandle()}': {$challengeType->getName()}";
            $definition = $challengeType->getConfigurationDefinition();
            if ($definition === []) {
                $help .= "\n    (this challenge does not accept any option)";
            } else {
                $help .= "\n    Supported options:";
                foreach ($definition as $option => $optionData) {
                    $help .= "\n    --{$option} {$optionData['description']}";
                }
            }
        }

        return $help;
    }
}
