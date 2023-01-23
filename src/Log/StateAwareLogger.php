<?php

namespace Acme\Log;

use Psr\Log\LogLevel;

defined('C5_EXECUTE') or die('Access Denied.');

abstract class StateAwareLogger extends Logger
{
    /**
     * The maximum logged level.
     *
     * @var string
     */
    protected $maxLevel = '';

    /**
     * Get the maximum logged level.
     *
     * @return string Empty string if nothing has been logged, otherwise one if the Psr\Log\LogLevel:... constants
     */
    public function getMaxLevel()
    {
        return $this->maxLevel;
    }

    /**
     * Check if the maximum logged level is at least a specific level.
     *
     * @param string $level one if the Psr\Log\LogLevel:... constants (if it's invalid, we'll return TRUE if we logged something)
     *
     * @return bool
     */
    public function hasMaxLevelAtLeast($level)
    {
        $maxLevel = $this->getMaxLevel();
        if ($maxLevel === '') {
            return false;
        }
        $levelComparable = $this->getComparableLevelNumber($level);
        if ($levelComparable === null) {
            return true;
        }

        return $this->getComparableLevelNumber($maxLevel) >= $levelComparable;
    }

    /**
     * Update the maxLevel property accordingly to a new logged level.
     *
     * @param string $level One of the Psr\Log\LogLevel:... constants
     * @param string|mixed $onInvalidLevel
     *
     * @return $this
     */
    protected function logLogLevel($level, $onInvalidLevel = LogLevel::EMERGENCY)
    {
        if ($this->getComparableLevelNumber($level) === null) {
            $level = $onInvalidLevel;
        }
        if ($this->maxLevel === '') {
            $this->maxLevel = $level;
        } elseif ($this->getComparableLevelNumber($level) > $this->getComparableLevelNumber($this->maxLevel)) {
            $this->maxLevel = $level;
        }

        return $this;
    }

    /**
     * Convert a PSR log level to a comparable number (higher number means higher priority).
     *
     * @param string $level One of the Psr\Log\LogLevel:... constants
     *
     * @return int|null
     */
    protected function getComparableLevelNumber($level)
    {
        static $map = [
            LogLevel::DEBUG => 1,
            LogLevel::INFO => 2,
            LogLevel::NOTICE => 3,
            LogLevel::WARNING => 4,
            LogLevel::ERROR => 5,
            LogLevel::CRITICAL => 6,
            LogLevel::ALERT => 7,
            LogLevel::EMERGENCY => 8,
        ];

        return isset($map[$level]) ? $map[$level] : null;
    }
}
