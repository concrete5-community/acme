<?php

namespace Acme\Editor;

use Acme\Entity\Certificate;
use Acme\Entity\CertificateAction;
use Acme\Entity\RemoteServer;
use Acme\Filesystem\DriverManager;
use Acme\Filesystem\ExecutableDriverInterface;
use Acme\Service\BooleanParser;
use ArrayAccess;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class to create/edit/delete CertificateAction entities.
 */
class CertificateActionEditor
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * @var \Acme\Filesystem\DriverManager
     */
    protected $filesystemDriverManager;

    /**
     * @var \Acme\Service\BooleanParser
     */
    protected $booleanParser;

    /**
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param \Acme\Filesystem\DriverManager $filesystemDriverManager
     * @param \Acme\Service\BooleanParser $booleanParser
     */
    public function __construct(EntityManagerInterface $em, DriverManager $filesystemDriverManager, BooleanParser $booleanParser)
    {
        $this->em = $em;
        $this->filesystemDriverManager = $filesystemDriverManager;
        $this->booleanParser = $booleanParser;
    }

    /**
     * Create a new CertificateAction instance.
     *
     * @param \Acme\Entity\Certificate $certificate the associated certificate
     * @param array $data Keys: 'position', 'remoteServer', 'savePrivateKey', 'savePrivateKeyTo', 'saveCertificate', 'saveCertificateTo', 'saveIssuerCertificate', 'saveIssuerCertificateTo', 'saveCertificateWithIssuer', 'saveCertificateWithIssuerTo', 'executeCommand', 'commandToExecute'
     * @param \ArrayAccess $errors Errors will be added here
     *
     * @return \Acme\Entity\CertificateAction|null NULL in case of errors
     */
    public function create(Certificate $certificate, array $data, ArrayAccess $errors)
    {
        $normalizedCreateData = $this->normalizeData($data, $errors, null, $certificate);
        if ($normalizedCreateData === null) {
            return null;
        }
        $certificateAction = CertificateAction::create($certificate);
        $this->applyNormalizedData($certificateAction, $normalizedCreateData);
        $this->em->transactional(function () use ($certificate, $certificateAction) {
            $this->em->persist($certificateAction);
            $this->em->flush($certificateAction);
            $certificate->setActionsState($certificate::ACTIONSTATE_NONE);
            $this->em->flush($certificateAction->getCertificate());
        });

        return $certificateAction;
    }

    /**
     * Edit an existing CertificateAction instance.
     *
     * @param \Acme\Entity\CertificateAction $certificateAction
     * @param array $data Keys: 'position', 'remoteServer', 'savePrivateKey', 'savePrivateKeyTo', 'saveCertificate', 'saveCertificateTo', 'saveIssuerCertificate', 'saveIssuerCertificateTo', 'saveCertificateWithIssuer', 'saveCertificateWithIssuerTo', 'executeCommand', 'commandToExecute'
     * @param \ArrayAccess $errors Errors will be added here
     *
     * @return bool FALSE in case of errors
     */
    public function edit(CertificateAction $certificateAction, array $data, ArrayAccess $errors)
    {
        $normalizedData = $this->normalizeData($data, $errors, $certificateAction);
        if ($normalizedData === null) {
            return false;
        }
        $this->applyNormalizedData($certificateAction, $normalizedData);
        $this->em->transactional(function () use ($certificateAction) {
            $certificate = $certificateAction->getCertificate();
            $this->em->flush($certificateAction);
            $certificate->setActionsState($certificate::ACTIONSTATE_NONE);
            $this->em->flush($certificate);
        });

        return true;
    }

    /**
     * Delete a CertificateAction instance.
     *
     * @param \Acme\Entity\CertificateAction $certificateAction
     * @param \ArrayAccess $errors
     *
     * @return bool FALSE in case of errors
     */
    public function delete(CertificateAction $certificateAction, ArrayAccess $errors)
    {
        $deletable = true;

        if ($deletable !== true) {
            return false;
        }
        $this->em->transactional(function () use ($certificateAction) {
            $certificate = $certificateAction->getCertificate();
            if ($certificate->getLastActionExecuted() === $certificateAction) {
                $certificate->setLastActionExecuted(null);
                $this->em->flush($certificate);
            }
            $this->em->remove($certificateAction);
            $this->em->flush($certificateAction);
        });

        return true;
    }

    /**
     * Extract/normalize the data received.
     *
     * @param array $data
     * @param \ArrayAccess $errors
     * @param \Acme\Entity\CertificateAction|null $certificateAction NULL if and only if creating a new instance
     * @param \Acme\Entity\Certificate|null $certificate NULL if and only if editing an existing instance
     *
     * @return array|null Return NULL in case of errors
     */
    protected function normalizeData(array $data, ArrayAccess $errors, CertificateAction $certificateAction = null, Certificate $certificate = null)
    {
        $state = new DataState($data, $errors);
        $normalizedData =
            [
                'position' => $this->extractPosition($state, $certificateAction, $certificate),
                'remoteServer' => $this->extractRemoteServer($state),
            ]
            + $this->extractBoolString($state, 'saveCertificate', 'saveCertificateTo')
            + $this->extractBoolString($state, 'saveIssuerCertificate', 'saveIssuerCertificateTo')
            + $this->extractBoolString($state, 'saveCertificateWithIssuer', 'saveCertificateWithIssuerTo')
            + $this->extractBoolString($state, 'savePrivateKey', 'savePrivateKeyTo')
            + $this->extractBoolString($state, 'executeCommand', 'commandToExecute')
        ;

        if ($state->isFailed() === false) {
            $this->checkExecuteCommand($state, $normalizedData);
        }
        $unknownKeys = $state->getRemainingKeys();
        if ($unknownKeys !== []) {
            $state->addError(t('Unrecognized keys detected:') . "\n- " . implode("\n- ", $unknownKeys));
        }

        return $state->isFailed() ? null : $normalizedData;
    }

    /**
     * Apply to a CertificateAction instance the data extracted from the normalizeData() method.
     *
     * @param \Acme\Entity\CertificateAction $certificateAction
     * @param array $normalizedData
     */
    protected function applyNormalizedData(CertificateAction $certificateAction, array $normalizedData)
    {
        $certificateAction
            ->setPosition($normalizedData['position'])
            ->setRemoteServer($normalizedData['remoteServer'])
            ->setIsSaveCertificate($normalizedData['saveCertificate'])->setSaveCertificateTo($normalizedData['saveCertificateTo'])
            ->setIsSaveIssuerCertificate($normalizedData['saveIssuerCertificate'])->setSaveIssuerCertificateTo($normalizedData['saveIssuerCertificateTo'])
            ->setIsSaveCertificateWithIssuer($normalizedData['saveCertificateWithIssuer'])->setSaveCertificateWithIssuerTo($normalizedData['saveCertificateWithIssuerTo'])
            ->setIsSavePrivateKey($normalizedData['savePrivateKey'])->setSavePrivateKeyTo($normalizedData['savePrivateKeyTo'])
            ->setIsExecuteCommand($normalizedData['executeCommand'])->setCommandToExecute($normalizedData['commandToExecute'])
        ;
    }

    /**
     * Extract 'position', checking that it's valid and that's not already used.
     *
     * @param \Acme\Editor\DataState $state
     * @param \Acme\Entity\CertificateAction|null $certificateAction NULL if and only if creating a new instance
     * @param \Acme\Entity\Certificate|null $certificate NULL if and only if editing an existing instance
     *
     * @return int|null
     */
    protected function extractPosition(DataState $state, CertificateAction $certificateAction = null, Certificate $certificate = null)
    {
        $value = (int) $state->popValue('position');
        if ($certificateAction !== null && $certificateAction->getPosition() === $value) {
            return $value;
        }
        if ($certificate === null) {
            $certificate = $certificateAction->getCertificate();
        }
        foreach ($certificate->getActions() as $otherAction) {
            if ($otherAction->getPosition() === $value) {
                $state->addError(t('The position %s is already used by another action', $value));

                return null;
            }
        }

        return $value;
    }

    /**
     * Extract 'remoteServer'.
     *
     * @param \Acme\Editor\DataState $state
     * @param null|CertificateAction $certificateAction
     * @param null|Certificate $certificate
     *
     * @return int|null
     */
    protected function extractRemoteServer(DataState $state, CertificateAction $certificateAction = null, Certificate $certificate = null)
    {
        $value = $state->popValue('remoteServer');
        if ($value instanceof RemoteServer) {
            return $value;
        }
        if (empty($value) || $value === '.') {
            return null;
        }
        if ((string) $value === (string) (int) $value) {
            $remoteServer = $this->em->find(RemoteServer::class, (int) $value);
            if ($remoteServer !== null) {
                return $remoteServer;
            }
        }

        $state->addError(t('The remote server is invalid'));

        return null;
    }

    /**
     * Extract a boolean flag and its associated string.
     *
     * @param \Acme\Editor\DataState $state
     * @param string $boolKey
     * @param string $stringKey
     *
     * @return array
     */
    protected function extractBoolString(DataState $state, $boolKey, $stringKey)
    {
        $boolValue = $this->booleanParser->toBoolean($state->popValue($boolKey));
        $stringValue = trim((string) $state->popValue($stringKey));
        if ($stringValue === '') {
            $boolValue = false;
        }

        return [
            $boolKey => $boolValue,
            $stringKey => $stringValue,
        ];
    }

    /**
     * Check if the remote server can execute commands.
     *
     * @param \Acme\Editor\DataState $state
     * @param array $normalizedData
     */
    protected function checkExecuteCommand(DataState $state, array $normalizedData)
    {
        $remoteServer = $normalizedData['remoteServer'];
        if ($remoteServer === null) {
            return;
        }
        if ($normalizedData['executeCommand'] === false || $normalizedData['commandToExecute'] === '') {
            return;
        }
        $driver = $this->filesystemDriverManager->getRemoteDriver($remoteServer);
        if (!$driver instanceof ExecutableDriverInterface) {
            $state->addError(t(
                "The '%1\$s' driver of the remote server '%2\$s' doesn't support executing commands",
                $this->filesystemDriverManager->getDriverName($remoteServer->getDriverHandle()),
                $remoteServer->getName()
            ));
        }
    }
}
