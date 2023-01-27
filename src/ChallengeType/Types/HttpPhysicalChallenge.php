<?php

namespace Acme\ChallengeType\Types;

use Acme\Entity\AuthorizationChallenge;
use Acme\Entity\Domain;
use Acme\Entity\RemoteServer;
use Acme\Exception\FilesystemException;
use Acme\Exception\RuntimeException;
use Acme\Filesystem\DriverManager as FilesystemDriverManager;
use Acme\Http\ClientFactory;
use Acme\Service\HttpTokenWriter;
use ArrayAccess;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

final class HttpPhysicalChallenge extends HttpChallenge
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var \Acme\Filesystem\DriverManager
     */
    private $filesystemDriverManager;

    /**
     * @var \Acme\Http\ClientFactory
     */
    private $httpClientFactory;

    /**
     * @var \Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface
     */
    private $resolverManager;

    public function __construct(EntityManagerInterface $em, FilesystemDriverManager $filesystemDriverManager, ClientFactory $httpClientFactory, ResolverManagerInterface $resolverManager)
    {
        $this->em = $em;
        $this->filesystemDriverManager = $filesystemDriverManager;
        $this->httpClientFactory = $httpClientFactory;
        $this->resolverManager = $resolverManager;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::getName()
     */
    public function getName()
    {
        return t('Create automatically a file in the webroot directory of this or another website');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::getConfigurationDefinition()
     */
    public function getConfigurationDefinition()
    {
        return [
            'server' => [
                'description' => 'The ID of the remote server hosting the website (use "." for the current server)',
                'defaultValue' => '.',
            ],
            'webroot' => [
                'description' => 'The absolute path to the directory containing the root of the website',
                'defaultValue' => '',
            ],
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
    public function checkConfiguration(Domain $domain, array $challengeConfiguration, array $previousChallengeConfiguration, ArrayAccess $errors)
    {
        if (parent::checkConfiguration($domain, $challengeConfiguration, $errors) === null) {
            return null;
        }
        $result = $this->normalizeChallengeConfiguration($challengeConfiguration);
        if ($result['nocheck']) {
            return $result;
        }
        $tokenWritten = false;
        try {
            $writer = $this->createTokenWriter($challengeConfiguration);
            $sampleContents = 'Physical/' . mt_rand() . '/' . time();
            for (;;) {
                $sampleToken = 'testPhysical_' . str_replace(['0.', ' '], ['', 's'], microtime(false)) . '_' . mt_rand();
                if (!$writer->getDriver()->isFile($writer->getAbsoluteTokenFilename($sampleToken))) {
                    break;
                }
            }
            $found = false;
            $writer->createTokenFile($sampleToken, $sampleContents);
            $tokenWritten = true;
            sleep(1);

            $urls = [];
            $reason = '';
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
                    $url .= '/' . $writer->getRelativeTokenFilename($sampleToken);
                    $urls[] = $url;
                    try {
                        $response = $httpClient->get($url);
                    } catch (RuntimeException $x) {
                        $reason = $reason ?: $x->getMessage();
                        $response = null;
                    }
                    if ($response !== null) {
                        if ($response->statusCode !== 200) {
                            $reason = t('Response code: %s (%s)', $response->statusCode, $response->reasonPhrase);
                        } elseif ($response->body !== $sampleContents) {
                            $reason = t('The returned content is wrong');
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
        } catch (UserMessageException $x) {
            $errors[] = $x->getMessage();

            return null;
        } catch (FilesystemException $x) {
            $errors[] = $x->getMessage();

            return null;
        } finally {
            if ($tokenWritten) {
                try {
                    $writer->deleteTokenFile($sampleToken);
                } catch (FilesystemException $x) {
                }
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::beforeChallenge()
     */
    public function beforeChallenge(AuthorizationChallenge $authorizationChallenge, LoggerInterface $logger)
    {
        $writer = $this->createTokenWriter($authorizationChallenge->getDomain()->getChallengeTypeConfiguration());
        $writer->createTokenFile($authorizationChallenge->getChallengeToken(), $authorizationChallenge->getChallengeAuthorizationKey());
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::isReadyForChallenge()
     */
    public function isReadyForChallenge(AuthorizationChallenge $authorizationChallenge, LoggerInterface $logger)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Acme\ChallengeType\ChallengeTypeInterface::afterChallenge()
     */
    public function afterChallenge(AuthorizationChallenge $authorizationChallenge, LoggerInterface $logger)
    {
        $writer = $this->createTokenWriter($authorizationChallenge->getDomain()->getChallengeTypeConfiguration());
        $writer->deleteTokenFile($authorizationChallenge->getChallengeToken());
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
            $server = array_get($data, 'server');
            $webroot = array_get($data, 'webroot');
            $nocheck = array_get($data, 'nocheck');
        } else {
            $server = '.';
            $webroot = '';
            $nocheck = false;
        }

        return [
            'server' => $server,
            'webroot' => $webroot,
            'nocheck' => $nocheck,
            'remoteServers' => $this->em->getRepository(RemoteServer::class)->findBy([], ['name' => 'ASC', 'id' => 'ASC']),
            'authorizationPorts' => $domain->getAccount()->getServer()->getAuthorizationPorts(),
            'remoteServersPage' => (string) $this->resolverManager->resolve(['/dashboard/system/acme/remote_servers']),
        ];
    }

    /**
     * @return array
     */
    private function normalizeChallengeConfiguration(array $challengeConfiguration)
    {
        $server = (int) array_get($challengeConfiguration, 'server');

        return [
            'webroot' => rtrim(str_replace('\\', '/', (string) array_get($challengeConfiguration, 'webroot')), '/'),
            'server' => $server === 0 ? '.' : $server,
            'nocheck' => (bool) array_get($challengeConfiguration, 'nocheck'),
        ];
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return \Acme\Service\HttpTokenWriter
     */
    private function createTokenWriter(array $challengeConfiguration)
    {
        $challengeConfiguration = $this->normalizeChallengeConfiguration($challengeConfiguration);
        $webroot = $challengeConfiguration['webroot'];
        $server = $challengeConfiguration['server'];

        if ($server === '.') {
            if ($webroot === '') {
                $webroot = rtrim(str_replace('\\', '/', DIR_BASE), '/');
            }
            $filesystem = $this->filesystemDriverManager->getLocalDriver();
        } else {
            if ($webroot === '') {
                throw new UserMessageException(t('Missing the path to the web root'));
            }
            $remoteServer = $this->em->find(RemoteServer::class, $server);
            if ($remoteServer === null) {
                throw new UserMessageException(t('Missing the server providing the website'));
            }
            $filesystem = $this->filesystemDriverManager->getRemoteDriver($remoteServer);
        }

        return new HttpTokenWriter($filesystem, $webroot);
    }
}
