<?php

namespace Acme\ChallengeType;

use Concrete\Core\Application\Application;
use Concrete\Core\Config\Repository\Repository;

defined('C5_EXECUTE') or die('Access Denied.');

class ChallengeTypeManager
{
    /**
     * @var \Concrete\Core\Config\Repository\Repository
     */
    protected $config;

    /**
     * @var \Concrete\Core\Application\Application
     */
    protected $app;

    /**
     * @param \Concrete\Core\Config\Repository\Repository $config
     * @param \Concrete\Core\Application\Application $app
     */
    public function __construct(Repository $config, Application $app)
    {
        $this->config = $config;
        $this->app = $app;
    }

    /**
     * Get the list of available challenge types.
     *
     * @return \Acme\ChallengeType\ChallengeTypeInterface[]
     */
    public function getChallengeTypes()
    {
        $list = [];
        foreach (array_keys($this->config->get('acme::challenge.types')) as $handle) {
            $list[] = $this->getChallengeByHandle($handle);
        }

        return $list;
    }

    /**
     * Get a challenge given its handle.
     *
     * @param string $handle The handle of the challenge
     *
     * @return \Acme\ChallengeType\ChallengeTypeInterface|null return NULL if the challenge could not be found
     */
    public function getChallengeByHandle($handle)
    {
        $data = $this->getChallengeDetails($handle);
        if ($data === null) {
            return null;
        }
        $challenge = $this->app->make($data['class']);
        $challenge->initialize($handle, $data['challengeTypeOptions']);

        return $challenge;
    }

    /**
     * Get the details of a challenge given its handle.
     *
     * @param string $handle
     *
     * @return array|null
     */
    protected function getChallengeDetails($handle)
    {
        $handle = (string) $handle;
        if ($handle === '') {
            return null;
        }
        $data = $this->config->get('acme::challenge.types.' . $handle);
        if (!is_array($data)) {
            return null;
        }
        $class = array_get($data, 'class');
        if (!is_string($class) || $class === '' || !is_a($class, ChallengeTypeInterface::class, true)) {
            return null;
        }
        unset($data['class']);

        return [
            'handle' => $handle,
            'class' => $class,
            'challengeTypeOptions' => $data,
        ];
    }
}
