<?php

namespace Acme\Editor;

use Acme\Entity\RemoteServer;
use Acme\Exception\FilesystemException;
use Acme\Filesystem\DriverManager;
use Acme\Filesystem\RemoteDriverInterface;
use Acme\Security\Crypto;
use ArrayAccess;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class to create/edit/delete remote server entities.
 */
class RemoteServerEditor
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * @var \Acme\Filesystem\DriverManager
     */
    protected $filesistemDriverManager;

    /**
     * @var \Acme\Security\Crypto
     */
    protected $crypto;

    /**
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param DriverManager $filesistemDriverManager
     * @param Crypto $crypto
     */
    public function __construct(EntityManagerInterface $em, DriverManager $filesistemDriverManager, Crypto $crypto)
    {
        $this->em = $em;
        $this->filesistemDriverManager = $filesistemDriverManager;
        $this->crypto = $crypto;
    }

    /**
     * Create a new RemoteServer instance.
     *
     * @param array $data Keys: '', 'password', 'privateKey', 'sshAgentSocket'
     * @param array $data Keys:<br />
     * - string <code><b>name</b></code> the mnemonic name of the remote server [required]<br />
     * - string <code><b>hostname</b></code> the host name/IP address of the remote server [required]<br />
     * - int|string|null <code><b>port</b></code> the port to be used to connect to the server [optional, if not specified we'll use the default one]<br />
     * - int|string|null <code><b>connectionTimeout</b></code> the connection timeout, in seconds [optional, if not specified we'll use the default one]<br />
     * - string <code><b>driver</b></code> the handle of the driver [required]<br />
     * - string <code><b>username</b></code> the username to be used to connect to the remote server [optional]<br />
     * - string <code><b>password</b></code> the password to be used to connect to the remote server [optional]<br />
     * - string <code><b>privateKey</b></code> the private key (in RSA format) to be used to connect to the remote server [optional]<br />
     * - string <code><b>sshAgentSocket</b></code> the name of the SSH Agent socket to be used to connect to the remote server [optional]
     * @param \ArrayAccess $errors Errors will be added here
     *
     * @return \Acme\Entity\RemoteServer|null NULL in case of errors
     */
    public function create(array $data, ArrayAccess $errors)
    {
        $normalizedData = $this->normalizeData($data, $errors);
        if ($normalizedData === null) {
            return null;
        }
        $remoteServer = RemoteServer::create();
        $this->applyNormalizedData($remoteServer, $normalizedData);
        $this->em->persist($remoteServer);
        $this->em->flush($remoteServer);

        return $remoteServer;
    }

    /**
     * Edit an existing RemoteServer instance.
     *
     * @param \Acme\Entity\RemoteServer $remoteServer
     * @param array $data Keys:<br />
     * - string <code><b>name</b></code> the mnemonic name of the remote server [required]<br />
     * - string <code><b>hostname</b></code> the host name/IP address of the remote server [required]<br />
     * - int|string|null <code><b>port</b></code> the port to be used to connect to the server [optional, if not specified we'll use the default one]<br />
     * - int|string|null <code><b>connectionTimeout</b></code> the connection timeout, in seconds [optional, if not specified we'll use the default one]<br />
     * - string <code><b>driver</b></code> the handle of the driver [required]<br />
     * - string <code><b>username</b></code> the username to be used to connect to the remote server [optional]<br />
     * - string <code><b>password</b></code> the password to be used to connect to the remote server [optional]<br />
     * - string <code><b>privateKey</b></code> the private key (in RSA format) to be used to connect to the remote server [optional]<br />
     * - string <code><b>sshAgentSocket</b></code> the name of the SSH Agent socket to be used to connect to the remote server [optional]
     * @param \ArrayAccess $errors Errors will be added here
     *
     * @return bool FALSE in case of errors
     */
    public function edit(RemoteServer $remoteServer, array $data, ArrayAccess $errors)
    {
        $normalizedData = $this->normalizeData($data, $errors, $remoteServer);
        if ($normalizedData === null) {
            return false;
        }
        $this->applyNormalizedData($remoteServer, $normalizedData);
        $this->em->flush($remoteServer);

        return true;
    }

    /**
     * Delete a RemoteServer instance.
     *
     * @param \Acme\Entity\RemoteServer $remoteServer
     * @param \ArrayAccess $errors
     *
     * @return bool FALSE in case of errors
     */
    public function delete(RemoteServer $remoteServer, ArrayAccess $errors)
    {
        $deletable = true;

        $numCertificateActions = $remoteServer->getCertificateActions()->count();
        if ($numCertificateActions > 0) {
            $errors[] = t2(
                "This remote server can't be deleted since it's used by %d certificate action",
                "This remote server can't be deleted since it's used by %d certificate actions",
                $numCertificateActions
            );
            $deletable = false;
        }

        if ($deletable !== true) {
            return false;
        }

        $this->em->remove($remoteServer);
        $this->em->flush($remoteServer);

        return true;
    }

    /**
     * Extract/normalize the data received.
     *
     * @param array $data
     * @param \ArrayAccess $errors
     * @param \Acme\Entity\RemoteServer|null $remoteServer
     *
     * @return array|null Return NULL in case of errors
     */
    protected function normalizeData(array $data, ArrayAccess $errors, RemoteServer $remoteServer = null)
    {
        $state = new DataState($data, $errors);
        $normalizedData = [
            'name' => $this->extractName($state, $remoteServer),
            'hostname' => $this->extractHostname($state),
            'port' => $this->extractPort($state),
            'connectionTimeout' => $this->extractConnectionTimeout($state),
        ] + $this->extractDriver($state);

        $unknownKeys = $state->getRemainingKeys();
        if ($unknownKeys !== []) {
            $state->addError(t('Unrecognized keys detected:') . "\n- " . implode("\n- ", $unknownKeys));
        }

        if ($state->isFailed() === false) {
            $this->checkConnectionParameters($state, $normalizedData);
        }

        return $state->isFailed() ? null : $normalizedData;
    }

    /**
     * Apply to a RemoteServer instance the data extracted from the normalizeData() method.
     *
     * @param \Acme\Entity\RemoteServer $remoteServer
     * @param array $normalizedData
     */
    protected function applyNormalizedData(RemoteServer $remoteServer, array $normalizedData)
    {
        $remoteServer
            ->setName($normalizedData['name'])
            ->setHostname($normalizedData['hostname'])
            ->setPort($normalizedData['port'])
            ->setConnectionTimeout($normalizedData['connectionTimeout'])
            ->setDriverHandle($normalizedData['driver'])
            ->setUsername($normalizedData['username'])
            ->setPassword($normalizedData['password'])
            ->setPrivateKey($normalizedData['privateKey'])
            ->setSshAgentSocket($normalizedData['sshAgentSocket'])
        ;
    }

    /**
     * @param \Acme\Editor\DataState $state
     * @param array $normalizedData
     */
    protected function checkConnectionParameters(DataState $state, array $normalizedData)
    {
        $remoteServer = RemoteServer::create();
        $this->applyNormalizedData($remoteServer, $normalizedData);
        try {
            $this->filesistemDriverManager->getRemoteDriver($remoteServer)->checkConnection();
        } catch (FilesystemException $error) {
            $state->addError($error);
        }
    }

    /**
     * Extract 'name', checking that it's valid and that's not already used.
     *
     * @param \Acme\Editor\DataState $state
     * @param \Acme\Entity\RemoteServer|null $remoteServer NULL if creating a new remote server
     *
     * @return string
     */
    protected function extractName(DataState $state, RemoteServer $remoteServer = null)
    {
        $value = $state->popValue('name');
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            $state->addError(t('The mnemonic name of the remote server is missing'));

            return '';
        }

        if ($value === '.') {
            $state->addError(t("The mnemonic name of the remote server can't be a dot"));

            return '';
        }
        if ($value === (string) ((int) $value)) {
            $state->addError(t("The mnemonic name of the remote server can't be an integer number"));

            return '';
        }
        if (!in_array($this->em->getRepository(RemoteServer::class)->findOneBy(['name' => $value]), [null, $remoteServer], true)) {
            $state->addError(t("There's already another remote server with a '%s' mnemonic name", $value));

            return '';
        }

        return $value;
    }

    /**
     * Extract 'hostname', checking that it's valid.
     *
     * @param \Acme\Editor\DataState $state
     *
     * @return string
     */
    protected function extractHostname(DataState $state)
    {
        $value = $state->popValue('hostname');
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            $state->addError(t('The host name/IP address of the remote server is missing.'));

            return '';
        }

        return $value;
    }

    /**
     * Extract 'port', checking that it's valid.
     *
     * @param \Acme\Editor\DataState $state
     *
     * @return int|null
     */
    protected function extractPort(DataState $state)
    {
        $value = (string) $state->popValue('port');
        if ($value === '') {
            return null;
        }
        if ($value !== (string) (int) $value) {
            $state->addError(t('The connection port must be an integer number.'));

            return null;
        }
        $value = (int) $value;
        if ($value < 0x0001 && $value > 0xffff) {
            $state->addError(t('The connection port must be an integer number between %1$s and %2$s.', 0x0001, 0xffff));

            return null;
        }

        return $value;
    }

    /**
     * Extract 'connectionTimeout', checking that it's valid.
     *
     * @param \Acme\Editor\DataState $state
     *
     * @return int|null
     */
    protected function extractConnectionTimeout(DataState $state)
    {
        $value = (string) $state->popValue('connectionTimeout');
        if ($value === '') {
            return null;
        }
        if ($value !== (string) (int) $value) {
            $state->addError(t('The connection timeout must be an integer number.'));

            return null;
        }
        $value = (int) $value;
        if ($value < 1) {
            $state->addError(t('The connection timeout must be an integer number greather than zero.'));

            return null;
        }

        return $value;
    }

    /**
     * Extract 'driver', 'username', 'password', 'privateKey', 'sshAgentSocket'.
     *
     * @param \Acme\Editor\DataState $state
     *
     * @return string
     */
    protected function extractDriver(DataState $state)
    {
        $result = [
            'driver' => '',
            'username' => '',
            'password' => '',
            'privateKey' => '',
            'sshAgentSocket' => '',
        ];
        $driver = $state->popValue('driver');
        $username = $state->popValue('username');
        $password = $state->popValue('password');
        $privateKey = $state->popValue('privateKey');
        $sshAgentSocket = $state->popValue('sshAgentSocket');

        $driver = is_string($driver) ? trim($driver) : '';
        if ($driver === '') {
            $state->addError(t('Missing the driver for the remote server'));

            return $result;
        }
        $drivers = $this->filesistemDriverManager->getDrivers(null, RemoteDriverInterface::class);
        if (!isset($drivers[$driver])) {
            $state->addError(t('Unknown remote server driver: %s', $driver));

            return $result;
        }
        $driverInfo = $drivers[$driver];
        if (!$driverInfo['available']) {
            $state->addError(t("The remote server driver '%s' is not available", driverInfo['name']));

            return $result;
        }
        $result['driver'] = $driver;
        if ($driverInfo['loginFlags'] & RemoteDriverInterface::LOGINFLAG_USERNAME) {
            $result['username'] = is_string($username) ? $username : '';
        }
        if ($driverInfo['loginFlags'] & RemoteDriverInterface::LOGINFLAG_PASSWORD) {
            $result['password'] = is_string($password) ? $password : '';
        }
        if ($driverInfo['loginFlags'] & RemoteDriverInterface::LOGINFLAG_PRIVATEKEY) {
            $privateKey = is_string($privateKey) ? $privateKey : '';
            if ($privateKey === '') {
                $state->addError(t('Missing the private key for the remote server driver'));

                return $result;
            }
            if ($this->crypto->getKeyPairFromPrivateKey($privateKey) === null) {
                $state->addError(t('The private key for the remote server driver is malformed'));

                return $result;
            }
            $result['privateKey'] = $privateKey;
        }
        if ($driverInfo['loginFlags'] & RemoteDriverInterface::LOGINFLAG_PASSWORD) {
            $result['sshAgentSocket'] = is_string($sshAgentSocket) ? $sshAgentSocket : '';
        }

        return $result;
    }
}
