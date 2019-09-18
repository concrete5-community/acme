<?php

namespace Acme\ChallengeType\Types;

use Acme\Entity\Domain;
use Acme\Http\AuthorizationMiddleware;
use Acme\Security\Crypto;
use ArrayAccess;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Http\Client\Client;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Zend\Http\Client\Exception\RuntimeException as ZendClientRuntimeException;

defined('C5_EXECUTE') or die('Access Denied.');

class HttpInterceptChallenge extends HttpChallenge
{
    /**
     * @var \Concrete\Core\Config\Repository\Repository
     */
    protected $config;

    /**
     * @var \Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface
     */
    protected $resolverManager;

    /**
     * @var \Concrete\Core\Http\Client\Client
     */
    protected $httpClient;

    /**
     * @param \Concrete\Core\Config\Repository\Repository $config
     * @param \Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
     * @param \Concrete\Core\Http\Client\Client $httpClient
     * @param \Acme\Security\Crypto $crypto
     */
    public function __construct(Repository $config, ResolverManagerInterface $resolverManager, Client $httpClient, Crypto $crypto)
    {
        parent::__construct($crypto);
        $this->config = $config;
        $this->resolverManager = $resolverManager;
        $this->httpClient = $httpClient;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::getName()
     */
    public function getName()
    {
        return t('Intercept calls to this website');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::getConfigurationDefinition()
     */
    public function getConfigurationDefinition()
    {
        return [
            'nocheck' => [
                'description' => t("Don't check the configuration when updating a domain"),
                'defaultValue' => false,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::checkConfiguration()
     */
    public function checkConfiguration(Domain $domain, array $challengeConfiguration, ArrayAccess $errors)
    {
        if (parent::checkConfiguration($domain, $challengeConfiguration, $errors) === null) {
            return null;
        }
        $result = [
            'nocheck' => (bool) array_get($challengeConfiguration, 'nocheck'),
        ];
        if ($result['nocheck']) {
            return $result;
        }

        $wantedContents = sha1($this->config->get('acme::site.unique_installation_id'));
        $urls = [];
        $reason = '';
        $found = false;
        foreach ($domain->getAccount()->getServer()->getAuthorizationPorts() as $port) {
            $url = 'http://' . $domain->getPunycode();
            if ($port !== 80) {
                $url .= ':' . $port;
            }
            $url .= AuthorizationMiddleware::ACME_CHALLENGE_PREFIX . AuthorizationMiddleware::ACME_CHALLENGE_TOKEN_TESTINTERCEPT;
            $urls[] = $url;
            try {
                $response = $this->httpClient->reset()->setMethod('GET')->setUri($url)->send();
            } catch (ZendClientRuntimeException $x) {
                $reason = $reason ?: $x->getMessage();
                $response = null;
            }
            if ($response !== null) {
                if (!$response->isOk()) {
                    $reason = t('Response code: %s (%s)', $response->getStatusCode(), $response->getReasonPhrase());
                } elseif ($response->getBody() !== $wantedContents) {
                    $reason = t("The returned content is wrong (maybe it's another concrete5 installation?)");
                } else {
                    $found = true;
                    break;
                }
            }
        }
        if (!$found) {
            $errors[] = t("The web server did not respond correctly at the following URL(s):\n%s.", implode("\n", $urls)) . "\n\n" . t('Reason: %s', $reason);

            return null;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::beforeChallenge()
     */
    public function beforeChallenge(Domain $domain)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::afterChallenge()
     */
    public function afterChallenge(Domain $domain)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\Types\HttpChallenge::getDomainConfigurationElementData()
     */
    protected function getDomainConfigurationElementData(Domain $domain)
    {
        if ($domain->getChallengeTypeHandle() === $this->getHandle()) {
            $data = $domain->getChallengeTypeConfiguration();
            $nocheck = array_get($data, 'nocheck');
        } else {
            $nocheck = false;
        }

        return [
            'nocheck' => $nocheck,
            'isInstalledInWebroot' => trim(DIR_REL, '/') === '',
            'isPrettyUrlEnabled' => (bool) $this->config->get('concrete.seo.url_rewriting'),
            'seoUrlsPage' => (string) $this->resolverManager->resolve(['/dashboard/system/seo/urls']),
        ];
    }
}
