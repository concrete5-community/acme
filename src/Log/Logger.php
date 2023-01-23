<?php

namespace Acme\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

defined('C5_EXECUTE') or die('Access Denied.');

abstract class Logger implements LoggerInterface
{
    /**
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::emergency()
     */
    public function emergency($message, array $context = [])
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::alert()
     */
    public function alert($message, array $context = [])
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::critical()
     */
    public function critical($message, array $context = [])
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::error()
     */
    public function error($message, array $context = [])
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::warning()
     */
    public function warning($message, array $context = [])
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::notice()
     */
    public function notice($message, array $context = [])
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::info()
     */
    public function info($message, array $context = [])
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::debug()
     */
    public function debug($message, array $context = [])
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Call the emergency() method and return this instance.
     *
     * @return $this
     *
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::emergency()
     */
    public function chainEmergency($message, array $context = [])
    {
        $this->emergency($message, $context);

        return $this;
    }

    /**
     * Call the alert() method and return this instance.
     *
     * @return $this
     *
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::alert()
     */
    public function chainAlert($message, array $context = [])
    {
        $this->alert($message, $context);

        return $this;
    }

    /**
     * Call the critical() method and return this instance.
     *
     * @return $this
     *
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::critical()
     */
    public function chainCritical($message, array $context = [])
    {
        $this->critical($message, $context);

        return $this;
    }

    /**
     * Call the error() method and return this instance.
     *
     * @return $this
     *
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::error()
     */
    public function chainError($message, array $context = [])
    {
        $this->error($message, $context);

        return $this;
    }

    /**
     * Call the warning() method and return this instance.
     *
     * @return $this
     *
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::warning()
     */
    public function chainWarning($message, array $context = [])
    {
        $this->warning($message, $context);

        return $this;
    }

    /**
     * Call the notice() method and return this instance.
     *
     * @return $this
     *
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::notice()
     */
    public function chainNotice($message, array $context = [])
    {
        $this->notice($message, $context);

        return $this;
    }

    /**
     * Call the info() method and return this instance.
     *
     * @return $this
     *
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::info()
     */
    public function chainInfo($message, array $context = [])
    {
        $this->info($message, $context);

        return $this;
    }

    /**
     * Call the debug() method and return this instance.
     *
     * @return $this
     *
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::debug()
     */
    public function chainDebug($message, array $context = [])
    {
        $this->debug($message, $context);

        return $this;
    }

    /**
     * Call the log() method and return this instance.
     *
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::log()
     */
    public function chainLog($level, $message, array $context = [])
    {
        $this->log($level, $message, $context);

        return $this;
    }

    /**
     * Log the start of a section.
     *
     * @param string $title
     *
     * @return $this
     */
    abstract public function section($title);

    /**
     * Add to this log the logs of another logger.
     *
     * @return $this
     */
    public function logLogger(ArrayLogger $arrayLogger)
    {
        foreach ($arrayLogger->getEntries() as $entry) {
            $this->log($entry->getLevel(), $entry->getMessage(), $entry->getContext());
        }

        return $this;
    }
}
