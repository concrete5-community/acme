<?php

namespace Acme\Editor;

use Acme\Account\RegistrationService;
use Acme\Entity\Account;
use Acme\Entity\Server;
use Acme\Exception\Exception;
use Acme\Security\Crypto;
use Acme\Service\BooleanParser;
use Acme\Service\NotificationSilencerTrait;
use ArrayAccess;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Validator\String\EmailValidator;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class to create/edit/delete ACME account entities.
 */
class AccountEditor
{
    use NotificationSilencerTrait;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * @var \Concrete\Core\Validator\String\EmailValidator
     */
    protected $emailValidator;

    /**
     * @var \Acme\Security\Crypto
     */
    protected $crypto;

    /**
     * @var \Acme\Account\RegistrationService
     */
    protected $registrationService;

    /**
     * @var \Acme\Service\BooleanParser
     */
    protected $booleanParser;

    /**
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param \Concrete\Core\Validator\String\EmailValidator $emailValidator
     * @param \Acme\Security\Crypto $crypto
     * @param \Acme\Account\RegistrationService $registrationService
     * @param \Acme\Service\BooleanParser $booleanParser
     */
    public function __construct(EntityManagerInterface $em, EmailValidator $emailValidator, Crypto $crypto, RegistrationService $registrationService, BooleanParser $booleanParser)
    {
        $this->em = $em;
        $this->emailValidator = $emailValidator;
        $this->crypto = $crypto;
        $this->registrationService = $registrationService;
        $this->booleanParser = $booleanParser;
    }

    /**
     * Create a new Account instance.
     *
     * @param \Acme\Entity\Server $server the associated server
     * @param array $data Keys:<br />
     * - string <code><b>name</b></code> the mnemonic name of the account [required]<br />
     * - string <code><b>email</b></code> the account email address [required]<br />
     * - boolean|mixed <code><b>default</b></code> should the account be the default one? [optional, default: false]<br />
     * - string|boolean <code><b>acceptedTermsOfService</b></code> the URL of the terms of service accepted, or a boolean stating that the user accepted them [optional if the server does not require it]<br />
     * - boolean|string <code><b>useExisting</b></code> set to true (or 'yes', 'y', '1', ...) to use an existing account [optional, default: false]<br />
     * - int <code><b>privateKeyBits</b></code> the number of bits of the private key to be created when useExisting is falsy [optional]<br />
     * - string <code><b>privateKey</b></code> the private key of the existing user [if and only if useExisting is true]
     * @param \ArrayAccess $errors Errors will be added here
     *
     * @throws \Acme\Exception\UnrecognizedProtocolVersionException when the ACME Protocol version is not recognized
     *
     * @return \Acme\Entity\Account|null NULL in case of errors
     */
    public function create(Server $server, array $data, ArrayAccess $errors)
    {
        $normalizedCreateData = $this->normalizeCreateData($server, $data, $errors);
        if ($normalizedCreateData === null) {
            return null;
        }
        $account = Account::create($server);
        $this->applyNormalizedCreateData($account, $normalizedCreateData);
        $this->em->transactional(function () use (&$account, $normalizedCreateData, $errors) {
            try {
                $this->registrationService->registerAccount($account, $normalizedCreateData['acceptedTermsOfService'], $normalizedCreateData['useExisting']);
            } catch (Exception $x) {
                $errors[] = $x->getMessage();
                $account = null;

                return;
            }
            $this->em->persist($account);
            $this->em->flush($account);
            if ($account->isDefault()) {
                foreach ($this->em->getRepository(Account::class)->findBy(['isDefault' => true]) as $a) {
                    if ($a !== $account) {
                        $a->setIsDefault(false);
                        $this->em->flush($a);
                    }
                }
            }
        });

        return $account;
    }

    /**
     * Edit an existing Account instance.
     *
     * @param \Acme\Entity\Account $account
     * @param array $data Keys:<br />
     * - string <code><b>name</b></code> the mnemonic name of the account [required]<br />
     * - boolean|mixed <code><b>default</b></code> should the account be the default one? [optional, default: false]
     * @param \ArrayAccess $errors Errors will be added here
     *
     * @return bool FALSE in case of errors
     */
    public function edit(Account $account, array $data, ArrayAccess $errors)
    {
        $normalizedEditData = $this->normalizeEditData($data, $errors, $account);
        if ($normalizedEditData === null) {
            return false;
        }
        $wasDefault = $account->isDefault();
        $this->applyNormalizedEditData($account, $normalizedEditData);
        $this->em->transactional(function () use ($account, $wasDefault) {
            $this->em->flush($account);
            if ($wasDefault !== $account->isDefault()) {
                if ($wasDefault) {
                    $newDefaultSet = false;
                    foreach ($this->em->getRepository(Account::class)->findBy(['server' => $account->getServer()], ['id' => 'desc']) as $a) {
                        if ($a !== $account) {
                            $a->setIsDefault(true);
                            $this->em->flush($a);
                            $newDefaultSet = true;
                            break;
                        }
                    }
                    if ($newDefaultSet === false) {
                        foreach ($this->em->getRepository(Account::class)->findBy([], ['id' => 'desc']) as $a) {
                            if ($a !== $account) {
                                $a->setIsDefault(true);
                                $this->em->flush($a);
                                break;
                            }
                        }
                    }
                } else {
                    foreach ($this->em->getRepository(Account::class)->findBy(['isDefault' => true]) as $a) {
                        if ($a !== $account) {
                            $a->setIsDefault(false);
                            $this->em->flush($a);
                        }
                    }
                }
            }
        });

        return true;
    }

    /**
     * Delete an Account instance.
     *
     * @param \Acme\Entity\Account $account
     * @param \ArrayAccess $errors
     *
     * @return bool FALSE in case of errors
     */
    public function delete(Account $account, ArrayAccess $errors)
    {
        $deletable = true;

        $numDomains = $account->getDomains()->count();
        if ($numDomains > 0) {
            $errors[] = t2(
                "It's not possible to delete the ACME account since it's associated to %s domain",
                "It's not possible to delete the ACME account since it's associated to %s domains",
                $numDomains
            );
            $deletable = false;
        }

        $numCertificates = $account->getCertificates()->count();
        if ($numCertificates > 0) {
            $errors[] = t2(
                "It's not possible to delete the ACME account since it's associated to %s certificate",
                "It's not possible to delete the ACME account since it's associated to %s certificates",
                $numCertificates
            );
            $deletable = false;
        }

        if ($deletable !== true) {
            return false;
        }

        $this->em->transactional(function () use ($account) {
            if ($account->isDefault()) {
                $newDefaultSet = false;
                foreach ($this->em->getRepository(Account::class)->findBy(['server' => $account->getServer()], ['id' => 'desc']) as $a) {
                    if ($a !== $account) {
                        $a->setIsDefault(true);
                        $this->em->flush($a);
                        $newDefaultSet = true;
                        break;
                    }
                }
                if ($newDefaultSet === false) {
                    foreach ($this->em->getRepository(Account::class)->findBy([], ['id' => 'desc']) as $a) {
                        if ($a !== $account) {
                            $a->setIsDefault(true);
                            $this->em->flush($a);
                            break;
                        }
                    }
                }
            }
            $this->em->remove($account);
            $this->em->flush($account);
        });

        return true;
    }

    /**
     * Extract/normalize the data received when creating a new ACME account.
     *
     * @param \Acme\Entity\Server $server
     * @param array $data
     * @param \ArrayAccess $errors
     *
     * @return array|null Return NULL in case of errors
     */
    protected function normalizeCreateData(Server $server, array $data, ArrayAccess $errors)
    {
        $state = new DataState($data, $errors);
        $normalizedData = [
            'name' => $this->extractName($state),
            'email' => $this->extractEmail($state),
            'default' => $this->extractDefault($state),
            'acceptedTermsOfService' => $this->extractAcceptedTermsOfService($state, $server),
        ] + $this->extractAccess($state);

        $unknownKeys = $state->getRemainingKeys();
        if ($unknownKeys !== []) {
            $state->addError(t('Unrecognized keys detected:') . "\n- " . implode("\n- ", $unknownKeys));
        }

        return $state->isFailed() ? null : $normalizedData;
    }

    /**
     * Apply to an Account instance the data extracted from the normalizeCreateData() method.
     *
     * @param \Acme\Entity\Account $account
     * @param array $normalizedCreateData
     */
    protected function applyNormalizedCreateData(Account $account, array $normalizedCreateData)
    {
        $account
            ->setName($normalizedCreateData['name'])
            ->setEmail($normalizedCreateData['email'])
            ->setIsDefault($normalizedCreateData['default'])
            ->setKeyPair($normalizedCreateData['keyPair'])
        ;
    }

    /**
     * Extract/normalize the data received when editing an existing ACME account.
     *
     * @param \Acme\Entity\Server $server
     * @param array $data
     * @param \ArrayAccess $errors
     * @param Account $account
     *
     * @return array|null Return NULL in case of errors
     */
    protected function normalizeEditData(array $data, ArrayAccess $errors, Account $account)
    {
        $state = new DataState($data, $errors);

        $normalizedData = [
            'name' => $this->extractName($state, $account),
            'default' => $this->extractDefault($state, $account),
        ];

        $unknownKeys = $state->getRemainingKeys();
        if ($unknownKeys !== []) {
            $state->addError(t('Unrecognized keys detected:') . "\n- " . implode("\n- ", $unknownKeys));
        }

        return $state->isFailed() ? null : $normalizedData;
    }

    /**
     * Apply to an Account instance the data extracted from the normalizeEditData() method.
     *
     * @param \Acme\Entity\Account $account
     * @param array $normalizedEditData
     */
    protected function applyNormalizedEditData(Account $account, array $normalizedEditData)
    {
        $account
            ->setName($normalizedEditData['name'])
            ->setIsDefault($normalizedEditData['default'])
        ;
    }

    /**
     * Extract 'name', checking that it's valid and that's not already used.
     *
     * @param \Acme\Editor\DataState $state
     * @param \Acme\Entity\Account|null $account NULL if creating a new account
     *
     * @return string
     */
    protected function extractName(DataState $state, Account $account = null)
    {
        $value = $state->popValue('name');
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            $state->addError(t('The mnemonic name of the ACME account is missing'));

            return '';
        }
        if ($value === (string) ((int) $value)) {
            $state->addError(t("The mnemonic name of the ACME account can't be an integer number"));

            return '';
        }
        if (!in_array($this->em->getRepository(Account::class)->findOneBy(['name' => $value]), [null, $account], true)) {
            $state->addError(t("There's already another ACME account with a '%s' mnemonic name", $value));

            return '';
        }

        return $value;
    }

    /**
     * Extract 'email', checking that it's valid.
     *
     * @param \Acme\Editor\DataState $state
     *
     * @return string
     */
    protected function extractEmail(DataState $state)
    {
        $value = $state->popValue('email');
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            $state->addError(t('The email address of the ACME account is missing'));

            return '';
        }
        if (!$this->emailValidator->isValid($value, $state->getErrors())) {
            $state->setFailed();

            return '';
        }

        return $value;
    }

    /**
     * Extract 'default', forcing it to TRUE in case the new/current account must be the default one.
     *
     * @param \Acme\Editor\DataState $state
     * @param \Acme\Entity\Account|null $account NULL if creating a new account
     *
     * @return bool
     */
    protected function extractDefault(DataState $state, Account $account = null)
    {
        $value = $state->popValue('default');
        if ($this->em->getRepository(Account::class)->findOneBy([]) === $account) {
            return true;
        }

        return $this->booleanParser->toBoolean($value);
    }

    /**
     * Extract 'acceptedTermsOfService', checking that it's valid.
     *
     * @param \Acme\Editor\DataState $state
     * @param \Acme\Entity\Server $server
     *
     * @return string
     */
    protected function extractAcceptedTermsOfService(DataState $state, Server $server)
    {
        $value = $state->popValue('acceptedTermsOfService');
        $serverTermsOfService = $server->getTermsOfServiceUrl();
        if (is_bool($value)) {
            return $value ? $serverTermsOfService : '';
        }
        if ((string) $value === $serverTermsOfService) {
            return $serverTermsOfService;
        }

        return '';
    }

    /**
     * Extract 'useExisting', 'privateKey', 'privateKeyBits'.
     *
     * @param \Acme\Editor\DataState $state
     *
     * @return string
     */
    protected function extractAccess(DataState $state)
    {
        $result = [
            'useExisting' => $this->booleanParser->toBoolean($state->popValue('useExisting')),
        ];
        if ($result['useExisting']) {
            $result['keyPair'] = $this->parsePrivateKeyExisting($state);
            $state->popValue('privateKeyBits');
        } else {
            $result['keyPair'] = $this->parsePrivateKeyNew($state);
            $state->popValue('privateKey');
        }

        return $result;
    }

    /**
     * Extract 'privateKey', checking that it's a valid private key.
     *
     * @param \Acme\Editor\DataState $state
     *
     * @return \Acme\Security\KeyPair|null
     */
    protected function parsePrivateKeyExisting(DataState $state)
    {
        $privateKey = $state->popValue('privateKey');
        if (!is_string($privateKey) || $privateKey === '') {
            $state->addError(t('The private key of the existing account has not beed specified'));

            return null;
        }

        $keyPair = $this->crypto->getKeyPairFromPrivateKey($privateKey);
        if ($keyPair === null) {
            $state->addError(t('The specified private key of the existing account is not valid'));

            return null;
        }

        return $keyPair;
    }

    /**
     * Extract 'privateKeyBits', and create a new private/public key pair (if no previous errors occurred, so that we don't waste time).
     *
     * @param \Acme\Editor\DataState $state
     *
     * @return \Acme\Security\KeyPair|null
     */
    protected function parsePrivateKeyNew(DataState $state)
    {
        $privateKeyBits = $state->popValue('privateKeyBits');
        if ($state->isFailed()) {
            return null;
        }
        try {
            return $this->crypto->generateKeyPair($privateKeyBits);
        } catch (UserMessageException $x) {
            $state->addError($x);

            return null;
        }
    }
}
