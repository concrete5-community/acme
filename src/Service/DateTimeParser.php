<?php

namespace Acme\Service;

use Acme\Exception\RuntimeException;
use DateTime;
use DateTimeInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class DateTimeParser
{
    use NotificationSilencerTrait;

    /**
     * @param string|\DateTimeInterface $value
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \DateTime|null
     */
    public function toDateTime($value)
    {
        $timestamp = $this->toTimestamp($value);
        if ($timestamp === null) {
            return null;
        }
        $result = new DateTime();
        $result->setTimestamp($timestamp);

        return $result;
    }

    /**
     * @param mixed $value
     *
     * @throws \Acme\Exception\Exception
     *
     * @return int|null
     */
    protected function toTimestamp($value)
    {
        if (empty($value)) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return (int) $value->getTimestamp() ?: null;
        }
        if (is_int($value) || (is_string($value) && $value === (string) (int) $value)) {
            return (int) $value ?: null;
        }
        if (!is_string($value)) {
            throw new RuntimeException(t('Unrecognized type for a date/time value: %s', gettype($value)));
        }
        $timestamp = (int) $this->ignoringWarnings(function () use ($value) {
            return strtotime($value);
        });
        if ($timestamp !== 0) {
            return $timestamp;
        }
        $m = null;
        if (preg_match('/^(\d\d\d\d-\d\d-\d\dT\d\d:\d\d:\d\d)\.\d*(\D.*)?$/', $value, $m)) {
            $timestamp = (int) $this->ignoringWarnings(function () use ($m) {
                return strtotime($m[1] . $m[2]);
            });
            if ($timestamp !== 0) {
                return $timestamp;
            }
        }

        throw new RuntimeException(t('Unrecognized date/time string: %s', $value));
    }
}
