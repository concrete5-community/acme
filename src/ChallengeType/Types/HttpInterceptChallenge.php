<?php

namespace Acme\ChallengeType\Types;

use Acme\Entity\AuthorizationChallenge;
use Acme\Entity\Domain;
use Acme\Http\AuthorizationMiddleware;
use Acme\Http\ClientFactory;
use Acme\Security\Crypto;
use ArrayAccess;
use Concrete\Core\Config\Repository\Repository;
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
     * @var \Acme\Http\ClientFactory
     */
    protected $httpClientFactory;

    /**
     * @param \Concrete\Core\Config\Repository\Repository $config
     * @param \Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
     * @param \Acme\Http\ClientFactory $httpClientFactory
     * @param \Acme\Security\Crypto $crypto
     */
    public function __construct(Repository $config, ResolverManagerInterface $resolverManager, ClientFactory $httpClientFactory, Crypto $crypto)
    {
        parent::__construct($crypto);
        $this->config = $config;
        $this->resolverManager = $resolverManager;
        $this->httpClientFactory = $httpClientFactory;
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
        $httpClient = $this->httpClientFactory->getAcmeServerLikeClient();
        foreach ($domain->getAccount()->getServer()->getAuthorizationPorts() as $port) {
            switch ($port) {
                case 443:
                    $protocols = ['https', 'http'];
                    break;
                case 80:
                default:
                    $protocols = ['http', 'https'];
                    break;
            }
            foreach ($protocols as $protocol) {
                $url = $protocol . '://' . $domain->getPunycode();
                switch ($protocol) {
                    case 'http':
                        if ($port !== 80) {
                            $url .= ':' . $port;
                        }
                        break;
                    case 'https':
                        if ($port !== 443) {
                            $url .= ':' . $port;
                        }
                        break;
                }
                $url .= AuthorizationMiddleware::ACME_CHALLENGE_PREFIX . AuthorizationMiddleware::ACME_CHALLENGE_TOKEN_TESTINTERCEPT;
                $urls[] = $url;
                try {
                    $response = $httpClient->reset()->setMethod('GET')->setUri($url)->send();
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
    public function beforeChallenge(AuthorizationChallenge $authorizationChallenge)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::afterChallenge()
     */
    public function afterChallenge(AuthorizationChallenge $authorizationChallenge)
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
