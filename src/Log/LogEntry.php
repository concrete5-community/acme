<?php

namespace Acme\Log;

use JsonSerializable;

defined('C5_EXECUTE') or die('Access Denied.');

final class LogEntry implements JsonSerializable
{
    /**
     * The level of the log entry (the value of the LogLevel::... constants).
     *
     * @var string
     */
    private $level;

    /**
     * The log entry message.
     *
     * @var string
     */
    private $message;

    /**
     * The context of the log entry.
     *
     * @var array
     */
    private $context;

    private function __construct()
    {
    }

    /**
     * Create a new instance.
     *
     * @param string $level The level of the log entry (the value of the LogLevel::... constants).
     * @param string $message the log entry message
     * @param array $context the context of the log entry
     *
     * @return static
     */
    public static function create($level, $message, array $context = [])
    {
        $result = new static();
        $result->level = (string) $level;
        $result->message = (string) $message;
        $result->context = $context;

        return $result;
    }

    /**
     * Get the level of the log entry (the value of the LogLevel::... constants).
     *
     * @return string
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Get the log entry message.
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get the context of the log entry.
     *
     * @return array
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     *
     * @see \JsonSerializable::jsonSerialize()
     */
    public function jsonSerialize()
    {
        return [
            'level' => $this->getLevel(),
            'message' => $this->getMessage(),
            'context' => $this->getContext(),
        ];
    }
}
