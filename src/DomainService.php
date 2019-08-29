<?php

namespace Acme;

use Acme\ChallengeType\ChallengeTypeManager;
use Acme\Entity\Domain;

defined('C5_EXECUTE') or die('Access Denied.');

class DomainService
{
    /**
     * @var \Acme\ChallengeType\ChallengeTypeManager
     */
    protected $challengeTypeManager;

    /**
     * @param \Acme\ChallengeType\ChallengeTypeManager $challengeTypeManager
     */
    public function __construct(ChallengeTypeManager $challengeTypeManager)
    {
        $this->challengeTypeManager = $challengeTypeManager;
    }

    /**
     * @param \Acme\Entity\Domain $domain
     *
     * @return string
     */
    public function describeChallengeType(Domain $domain)
    {
        $challengeType = $this->challengeTypeManager->getChallengeByHandle($domain->getChallengeTypeHandle());
        if ($challengeType === null) {
            return '';
        }

        return $challengeType->getName();
    }
}
