<?php

namespace Acme\Exception;

use Exception as ExceptionBase;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Exception thrown when the protocol version of an ACME server is unrecognized.
 */
class UnrecognizedProtocolVersionException extends ExceptionBase
{
    /**
     * The unknown protocol version.
     *
     * @var string
     */
    protected $unknownProtocolVersion;

    /**
     * Create a new instance.
     *
     * @param string $unknownProtocolVersion the unknown protocol version
     *
     * @return static
     */
    public static function create($unknownProtocolVersion)
    {
        $unknownProtocolVersion = (string) $unknownProtocolVersion;
        $result = new static(t('Unrecognized ACME Protocol: %s', $unknownProtocolVersion));
        $result->unknownProtocolVersion = $unknownProtocolVersion;

        return $result;
    }

    /**
     * Get the unknown protocol version.
     *
     * @return string
     */
    public function getUnknownProtocolVersion()
    {
        return $this->unknownProtocolVersion;
    }
}
