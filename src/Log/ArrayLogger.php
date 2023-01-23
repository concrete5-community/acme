<?php

namespace Acme\Log;

use Psr\Log\LogLevel;

defined('C5_EXECUTE') or die('Access Denied.');

class ArrayLogger extends StateAwareLogger
{
    /**
     * The logged entries.
     *
     * @var \Acme\Log\LogEntry[]
     */
    protected $entries = [];

    /**
     * {@inheritdoc}
     *
     * @see \Psr\Log\LoggerInterface::log()
     */
    public function log($level, $message, array $context = [])
    {
        $this->addEntry(LogEntry::create($level, $message, $context));
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\Log\Logger::section()
     */
    public function section($title)
    {
        $prefix = $this->getEntriesCount() === 0 ? '' : "\n\n";
        $this->log(LogLevel::INFO, $prefix . '### ' . $title);

        return $this;
    }

    /**
     * Get the number of logged entries.
     *
     * @return int
     */
    public function getEntriesCount()
    {
        return count($this->entries);
    }

    /**
     * Get the logged entries.
     *
     * @return \Acme\Log\LogEntry[]
     */
    public function getEntries()
    {
        return $this->entries;
    }

    /**
     * Get a specific logged entry.
     *
     * @param int $index
     *
     * @return \Acme\Log\LogEntry|null
     */
    public function getEntry($index)
    {
        return isset($this->entries[$index]) ? $this->entries[$index] : null;
    }

    /**
     * Get the maximum logged level recorded after a specific logged entry.
     *
     * @param int $index the entry index where the analysis shoult start
     *
     * @return string Empty string if nothing has been logged, otherwise one if the Psr\Log\LogLevel:... constants
     */
    public function getMaxLevelSince($index)
    {
        $result = '';
        $maxLevelNum = null;
        for ($i = $index, $count = $this->getEntriesCount(); $i < $count; $i++) {
            $entry = $this->getEntry($i);
            $entryLevelNum = $this->getComparableLevelNumber($entry->getLevel());
            if ($maxLevelNum === null || $entryLevelNum > $maxLevelNum) {
                $result = $entry->getLevel();
                $maxLevelNum = $entryLevelNum;
            }
        }

        return $result;
    }

    /**
     * Check if the maximum logged level is at least a specific level after a specific logged entry.
     *
     * @param int $index the entry index where the analysis shoult start
     * @param string $level one if the Psr\Log\LogLevel:... constants (if it's invalid, we'll return TRUE if we logged something)
     *
     * @return bool
     */
    public function hasMaxLevelSince($index, $level)
    {
        $maxLevel = $this->getMaxLevelSince($index);
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
     * Log a new LogEntry.
     *
     * @return $this
     */
    private function addEntry(LogEntry $entry)
    {
        $this->logLogLevel($entry->getLevel());
        $this->entries[] = $entry;

        return $this;
    }
}
