<?php

namespace Acme\Log;

use Concrete\Core\Console\OutputStyle;
use Psr\Log\LogLevel;

defined('C5_EXECUTE') or die('Access Denied.');

final class ConsoleLogger extends StateAwareLogger
{
    /**
     * @var \Concrete\Core\Console\OutputStyle
     */
    private $output;

    public function __construct(OutputStyle $output)
    {
        $this->output = $output;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::log()
     */
    public function log($level, $message, array $context = [])
    {
        $this->logLogLevel($level);
        switch ($level) {
            case LogLevel::DEBUG:
                if ($this->output->isVerbose()) {
                    $this->output->writeln($message);
                }
                break;
            case LogLevel::INFO:
            case LogLevel::NOTICE:
                if (!$this->output->isQuiet()) {
                    $this->output->writeln($message);
                }
                break;
            case LogLevel::WARNING:
                $this->output->warning($message);
                break;
            case LogLevel::ERROR:
            case LogLevel::CRITICAL:
            case LogLevel::ALERT:
            case LogLevel::EMERGENCY:
                default:
                $this->output->error($message);
                break;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Log\Logger::section()
     */
    public function section($message)
    {
        $this->output->section($message);
    }
}
