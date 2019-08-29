<?php

namespace Acme\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * A logger that forward logs to other loggers.
 */
class CombinedLogger extends StateAwareLogger
{
    /**
     * The loggers that will receive the new entries.
     *
     * @var \Psr\Log\LoggerInterface[]
     */
    protected $loggers = [];

    /**
     * Add a new logger to the loggers that will receive the new entries.
     *
     * @param \Psr\Log\LoggerInterface $logger
     *
     * @return $this
     */
    public function addLogger(LoggerInterface $logger)
    {
        $this->loggers[] = $logger;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::log()
     */
    public function log($level, $message, array $context = [])
    {
        $this->logLogLevel($level);
        foreach ($this->loggers as $logger) {
            $logger->log($level, $message, $context);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Log\Logger::section()
     */
    public function section($title)
    {
        foreach ($this->loggers as $logger) {
            if ($logger instanceof Logger) {
                $logger->section($title);
            } else {
                $this->log(LogLevel::INFO, $title);
            }
        }

        return $this;
    }
}
