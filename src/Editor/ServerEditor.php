<?php

namespace Acme\Editor;

use Acme\Entity\Server;
use Acme\Exception\Exception;
use Acme\Server\DirectoryInfoService;
use Acme\Service\BooleanParser;
use Acme\Service\NotificationSilencerTrait;
use ArrayAccess;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class to create/edit/delete ACME server entities.
 */
final class ServerEditor
{
    use NotificationSilencerTrait;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var \Acme\Server\DirectoryInfoService
     */
    private $directoryInfoService;

    /**
     * @var \Acme\Service\BooleanParser
     */
    private $booleanParser;

    public function __construct(EntityManagerInterface $em, DirectoryInfoService $directoryInfoService, BooleanParser $booleanParser)
    {
        $this->em = $em;
        $this->directoryInfoService = $directoryInfoService;
        $this->booleanParser = $booleanParser;
    }

    /**
     * Create a new Server instance.
     *
     * @param array $data Keys:<br />
     *                    - string <code><b>name</b></code> the mnemonic name of the server [required]<br />
     *                    - string <code><b>directoryUrl</b></code> the directory URL of the server [required]<br />
     *                    - boolean|mixed <code><b>default</b></code> should the server be the default one? [optional, default: false]<br />
     *                    - int[]|string[]|string <code><b>authorizationPorts</b></code> list of HTTP authorization ports; if string, it'll be splitted at non-numeric characters [required]<br />
     *                    - boolean|mixed <code><b>allowUnsafeConnections</b></code> should we allow unsafe connections to the server? [optional, default: false]
     * @param \ArrayAccess $errors Errors will be added here
     *
     * @return \Acme\Entity\Server|null NULL in case of errors
     */
    public function create(array $data, ArrayAccess $errors)
    {
        $normalizedData = $this->normalizeData($data, $errors);
        if ($normalizedData === null) {
            return null;
        }
        $server = Server::create();
        $this->applyNormalizedData($server, $normalizedData);
        $this->em->transactional(function () use ($server) {
            $this->em->persist($server);
            $this->em->flush($server);
            if ($server->isDefault()) {
                foreach ($this->em->getRepository(Server::class)->findBy(['isDefault' => true]) as $s) {
                    if ($s !== $server) {
                        $s->setIsDefault(false);
                        $this->em->flush($s);
                    }
                }
            }
        });

        return $server;
    }

    /**
     * Edit an existing Server instance.
     *
     * @param array $data Keys:<br />
     *                    - string <code><b>name</b></code> the mnemonic name of the server [required]<br />
     *                    - string <code><b>directoryUrl</b></code> the directory URL of the server [required]<br />
     *                    - boolean|mixed <code><b>default</b></code> should the server be the default one? [optional, default: false]<br />
     *                    - int[]|string[]|string <code><b>authorizationPorts</b></code> list of HTTP authorization ports; if string, it'll be splitted at non-numeric characters [required]<br />
     *                    - boolean|mixed <code><b>allowUnsafeConnections</b></code> should we allow unsafe connections to the server? [optional, default: false]
     * @param \ArrayAccess $errors Errors will be added here
     *
     * @return bool FALSE in case of errors
     */
    public function edit(Server $server, array $data, ArrayAccess $errors)
    {
        $normalizedData = $this->normalizeData($data, $errors, $server);
        if ($normalizedData === null) {
            return false;
        }
        $wasDefault = $server->isDefault();
        $this->applyNormalizedData($server, $normalizedData);
        $this->em->transactional(function () use ($server, $wasDefault) {
            $this->em->flush($server);
            if ($wasDefault !== $server->isDefault()) {
                if ($wasDefault) {
                    foreach ($this->em->getRepository(Server::class)->findBy([], ['id' => 'desc']) as $s) {
                        if ($s !== $server) {
                            $s->setIsDefault(true);
                            $this->em->flush($s);
                            break;
                        }
                    }
                } else {
                    foreach ($this->em->getRepository(Server::class)->findBy(['isDefault' => true]) as $s) {
                        if ($s !== $server) {
                            $s->setIsDefault(false);
                            $this->em->flush($s);
                        }
                    }
                }
            }
        });

        return true;
    }

    /**
     * Delete a Server instance.
     *
     * @return bool FALSE in case of errors
     */
    public function delete(Server $server, ArrayAccess $errors)
    {
        $deletable = true;

        $numAccounts = $server->getAccounts()->count();
        if ($numAccounts > 0) {
            $errors[] = t2(
                "It's not possible to delete the ACME Server since it's used by %s account",
                "It's not possible to delete the ACME Server since it's used by %s accounts",
                $numAccounts
            );
            $deletable = false;
        }

        if ($deletable !== true) {
            return false;
        }

        $this->em->transactional(function () use ($server) {
            if ($server->isDefault()) {
                foreach ($this->em->getRepository(Server::class)->findBy([], ['id' => 'desc']) as $s) {
                    if ($s !== $server) {
                        $s->setIsDefault(true);
                        $this->em->flush($s);
                        break;
                    }
                }
            }
            $this->em->remove($server);
            $this->em->flush($server);
        });

        return true;
    }

    /**
     * Extract/normalize the data received.
     *
     * @return array|null Return NULL in case of errors
     */
    private function normalizeData(array $data, ArrayAccess $errors, Server $server = null)
    {
        $state = new DataState($data, $errors);
        $normalizedData = [
            'name' => $this->extractName($state, $server),
            'directoryUrl' => $this->extractDirectoryUrl($state),
            'default' => $this->extractDefault($state, $server),
            'authorizationPorts' => $this->extractAuthorizationPorts($state),
            'allowUnsafeConnections' => $this->extractAllowUnsafeConnections($state),
        ];

        if ($state->isFailed() === false) {
            $normalizedData['directoryInfo'] = $this->getDirectoryInfo($state, $normalizedData);
        }

        $unknownKeys = $state->getRemainingKeys();
        if ($unknownKeys !== []) {
            $state->addError(t('Unrecognized keys detected:') . "\n- " . implode("\n- ", $unknownKeys));
        }

        return $state->isFailed() ? null : $normalizedData;
    }

    /**
     * Apply to a Server instance the data extracted from the normalizeData() method.
     */
    private function applyNormalizedData(Server $server, array $normalizedData)
    {
        $server
            ->setName($normalizedData['name'])
            ->setDirectoryUrl($normalizedData['directoryUrl'])
            ->setIsDefault($normalizedData['default'])
            ->setAuthorizationPorts($normalizedData['authorizationPorts'])
            ->setAllowUnsafeConnections($normalizedData['allowUnsafeConnections'])
            ->setProtocolVersion($normalizedData['directoryInfo']->getProtocolVersion())
            ->setNewNonceUrl($normalizedData['directoryInfo']->getNewNonceUrl())
            ->setNewAccountUrl($normalizedData['directoryInfo']->getNewAccountUrl())
            ->setNewAuthorizationUrl($normalizedData['directoryInfo']->getNewAuthorizationUrl())
            ->setNewCertificateUrl($normalizedData['directoryInfo']->getNewCertificateUrl())
            ->setNewOrderUrl($normalizedData['directoryInfo']->getNewOrderUrl())
            ->setRevokeCertificateUrl($normalizedData['directoryInfo']->getRevokeCertificateUrl())
            ->setTermsOfServiceUrl($normalizedData['directoryInfo']->getTermsOfServiceUrl())
            ->setWebsite($normalizedData['directoryInfo']->getWebsite())
        ;
    }

    /**
     * Extract 'name', checking that it's valid and that's not already used.
     *
     * @param \Acme\Entity\Server|null $server NULL if and only if creating a new server
     *
     * @return string
     */
    private function extractName(DataState $state, Server $server = null)
    {
        $value = $state->popValue('name');
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            $state->addError(t('The mnemonic name of the ACME server is missing'));

            return '';
        }
        if ($value === (string) ((int) $value)) {
            $state->addError(t("The mnemonic name of the ACME server can't be an integer number"));

            return '';
        }
        if (!in_array($this->em->getRepository(Server::class)->findOneBy(['name' => $value]), [null, $server], true)) {
            $state->addError(t("There's already another ACME server with a '%s' mnemonic name", $value));

            return '';
        }

        return $value;
    }

    /**
     * Extract 'directoryUrl', checking that it's a valid URL.
     *
     * @return string
     */
    private function extractDirectoryUrl(DataState $state)
    {
        $value = $state->popValue('directoryUrl');
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            $state->addError(t('The directory URL of the ACME server is missing.'));

            return '';
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $state->addError(t("The value of the directory URL of the ACME server ('%s') doesn't seems a valid URL.", $value));

            return '';
        }

        return $value;
    }

    /**
     * Extract 'default', forcing it to TRUE in case the new/current server must be the default one.
     *
     * @param \Acme\Entity\Server|null $server NULL if and only if creating a new server
     *
     * @return bool
     */
    private function extractDefault(DataState $state, Server $server = null)
    {
        $value = $state->popValue('default');
        if ($this->em->getRepository(Server::class)->findOneBy([]) === $server) {
            return true;
        }

        return $this->booleanParser->toBoolean($value);
    }

    /**
     * Extract 'authorizationPorts', checking that it's a valid list of ports.
     *
     * @return string
     */
    private function extractAuthorizationPorts(DataState $state)
    {
        $value = $state->popValue('authorizationPorts');
        if (is_string($value)) {
            $value = preg_split('/\D+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        } elseif (!is_array($value)) {
            $value = [];
        }
        $ports = array_values(array_unique(array_map('intval', $value)));
        if ($ports === []) {
            $state->addError(t('The list of auhorization ports of the ACME server is missing.'));

            return [];
        }
        $validPorts = array_values(array_filter(
            $ports,
            static function ($port) {
                return $port >= 0x0001 && $port <= 0xffff;
            }
        ));
        $invalidPorts = array_diff($ports, $validPorts);
        if ($invalidPorts !== []) {
            $state->addError(t('The list of auhorization ports of the ACME server is invalid (ports should be integers between %1$s and %2$s).', 0x0001, 0xffff));

            return [];
        }

        return $ports;
    }

    /**
     * Extract 'allowUnsafeConnections'.
     *
     * @return bool
     */
    private function extractAllowUnsafeConnections(DataState $state)
    {
        return $this->booleanParser->toBoolean($state->popValue('allowUnsafeConnections'));
    }

    /**
     * Detect the version of the protocol used.
     *
     * @return \Acme\Server\DirectoryInfo|null
     */
    private function getDirectoryInfo(DataState $state, array $normalizedData)
    {
        try {
            return $this->directoryInfoService->getInfoFromUrl($normalizedData['directoryUrl'], $normalizedData['allowUnsafeConnections']);
        } catch (Exception $x) {
            $state->addError($x->getMessage());

            return null;
        }
    }
}
