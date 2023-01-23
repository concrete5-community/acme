<?php

namespace Acme\Protocol;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class to manage the list and names of ACME protocol versions.
 */
final class Version
{
    /**
     * Protocol version handle: ACME version v1.
     *
     * @var string
     *
     * @see https://tools.ietf.org/id/draft-ietf-acme-acme-01.txt
     */
    const ACME_01 = 'acme_01';

    /**
     * Protocol version handle: ACME version v2.
     *
     * @var string
     *
     * @see https://tools.ietf.org/html/rfc8555
     */
    const ACME_02 = 'acme_02';

    /**
     * Get the list of available protocol versions.
     *
     * @return array keys are the protocol handle, values are their name
     */
    public function getAvailableProtocolVersions()
    {
        return [
            static::ACME_01 => 'ACME v1',
            static::ACME_02 => 'ACME v2',
        ];
    }

    /**
     * Get the name of a protocol version given its handle,.
     *
     * @param string $handle
     *
     * @return string empty string if $handle is not recognized
     */
    public function getProtocolVersionName($handle)
    {
        $versions = $this->getAvailableProtocolVersions();

        return isset($versions[$handle]) ? $versions[$handle] : '';
    }
}
