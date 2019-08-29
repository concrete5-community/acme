<?php

namespace Acme\ChallengeType;

use Acme\Entity\Domain;
use ArrayAccess;
use Concrete\Core\Filesystem\ElementManager;
use Concrete\Core\Page\Page;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Interface that all challenge types must implement.
 */
interface ChallengeTypeInterface
{
    /**
     * Initialize the instance (this will be called right after initializing the instance, so that you can place all the required dependencies in the class constructor).
     *
     * @param string $handle
     * @param array $challengeTypeOptions
     */
    public function initialize($handle, array $challengeTypeOptions);

    /**
     * Get the handle of this challenge type.
     *
     * @return string
     */
    public function getHandle();

    /**
     * Get the name of this challenge.
     *
     * @return string
     */
    public function getName();

    /**
     * Get the ACME type of this challenge.
     *
     * @return string
     *
     * @example 'http-01'
     * @example 'dns-01'
     */
    public function getAcmeTypeName();

    /**
     * Get the configuration definition of the challenge type (with *ALL* the supported options).
     * Array keys are the option name, array values are arrays with 'description' and 'defaultValue'.
     *
     * @return array return an empty array if (and only if) the challenge type doesn't have any configuration option
     */
    public function getConfigurationDefinition();

    /**
     * Check if this challenge is supported by a specific domain, and that the challenge configuration is valid.
     *
     * @param \Acme\Entity\Domain $domain
     * @param array $challengeConfiguration the challenge configuration as specified by the user
     * @param \ArrayAccess $errors add detected errors here

     *
     * @return array|null The normalized $challengeConfiguration, or NULL in case of errors
     */
    public function checkConfiguration(Domain $domain, array $challengeConfiguration, ArrayAccess $errors);

    /**
     * Get the element to be displayed in the web interface when configuring a domain.
     *
     * @param \Acme\Entity\Domain $domain
     * @param \Concrete\Core\Filesystem\ElementManager $elementManager
     * @param \Concrete\Core\Page\Page $page
     *
     * @return \Concrete\Core\Filesystem\Element
     */
    public function getDomainConfigurationElement(Domain $domain, ElementManager $elementManager, Page $page);

    /**
     * Method called right before initiating the authorization challenge.
     *
     * @param \Acme\Entity\Domain $domain
     */
    public function beforeChallenge(Domain $domain);

    /**
     * Method called right after terminating the authorization challenge.
     *
     * @param \Acme\Entity\Domain $domain
     */
    public function afterChallenge(Domain $domain);
}
