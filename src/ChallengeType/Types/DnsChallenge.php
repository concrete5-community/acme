<?php

namespace Acme\ChallengeType\Types;

use Acme\ChallengeType\ChallengeTypeInterface;
use Acme\Crypto\Hash;
use Acme\Service\Base64EncoderTrait;

defined('C5_EXECUTE') or die('Access Denied.');

abstract class DnsChallenge implements ChallengeTypeInterface
{
    use Base64EncoderTrait;

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::getAcmeTypeName()
     */
    public function getAcmeTypeName()
    {
        return 'dns-01';
    }

    /**
     * Generate the value to be saved in DNS records for dns-01 challenge types.
     *
     * @param string $authorizationKey
     *
     * @return string
     */
    protected function generateDnsRecordValue($authorizationKey)
    {
        $hasher = new Hash('sha256');
        $digest = $hasher->hash($authorizationKey);

        return $this->toBase64UrlSafe($digest);
    }
}
