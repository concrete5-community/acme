<?php

namespace Acme\ChallengeType\Types;

use Acme\ChallengeType\ChallengeTypeInterface;
use Acme\Entity\Domain;
use Acme\Security\Crypto;
use ArrayAccess;
use Concrete\Core\Filesystem\ElementManager;
use Concrete\Core\Page\Page;

defined('C5_EXECUTE') or die('Access Denied.');

abstract class HttpChallenge implements ChallengeTypeInterface
{
    /**
     * @var \Acme\Security\Crypto
     */
    protected $crypto;

    /**
     * @var string
     */
    protected $handle;

    /**
     * @param \Acme\Security\Crypto $crypto
     */
    public function __construct(Crypto $crypto)
    {
        $this->crypto = $crypto;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::initialize()
     */
    public function initialize($handle, array $challengeTypeOptions)
    {
        $this->handle = $handle;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::getHandle()
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::getAcmeTypeName()
     */
    public function getAcmeTypeName()
    {
        return 'http-01';
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::getDomainConfigurationElement()
     */
    public function getDomainConfigurationElement(Domain $domain, ElementManager $elementManager, Page $page)
    {
        return $elementManager->get(
            'challenge_type/' . $this->getHandle(),
            'acme',
            $page,
            ['challengeType' => $this] + $this->getDomainConfigurationElementData($domain)
        );
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::checkConfiguration()
     */
    public function checkConfiguration(Domain $domain, array $challengeConfiguration, ArrayAccess $errors)
    {
        if ($domain->isWildcard()) {
            $errors[] = t("HTTP challenges can't be performed on wildcard domains.");

            return null;
        }

        return [];
    }

    /**
     * Get extra data for the the element to be displayed in the web interface when configuring a domain.
     *
     * @param Domain $domain
     *
     * @return array
     */
    abstract protected function getDomainConfigurationElementData(Domain $domain);
}
