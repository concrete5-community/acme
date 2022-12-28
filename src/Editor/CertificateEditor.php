<?php

namespace Acme\Editor;

use Acme\Certificate\Revoker;
use Acme\Entity\Account;
use Acme\Entity\Certificate;
use Acme\Entity\CertificateDomain;
use Acme\Entity\RevokedCertificate;
use Acme\Exception\EntityNotFoundException;
use Acme\Finder;
use Acme\Security\Crypto;
use Acme\Service\BooleanParser;
use ArrayAccess;
use Concrete\Core\Error\UserMessageException;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class to create/edit/delete Certificate entities.
 */
class CertificateEditor
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * @var \Acme\Finder
     */
    protected $finder;

    /**
     * @var \Acme\Security\Crypto
     */
    protected $crypto;

    /**
     * @var \Acme\Certificate\Revoker
     */
    protected $revoker;

    /**
     * @var \Acme\Service\BooleanParser
     */
    protected $booleanParser;

    /**
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param \Acme\Finder $finder
     * @param \Acme\Security\Crypto $crypto
     * @param \Acme\Certificate\Revoker $revoker
     * @param \Acme\Service\BooleanParser $booleanParser
     */
    public function __construct(EntityManagerInterface $em, Finder $finder, Crypto $crypto, Revoker $revoker, BooleanParser $booleanParser)
    {
        $this->em = $em;
        $this->finder = $finder;
        $this->crypto = $crypto;
        $this->revoker = $revoker;
        $this->booleanParser = $booleanParser;
    }

    /**
     * Create a new Certificate instance.
     *
     * @param \Acme\Entity\Account $account the associated account
     * @param array $data Keys:<br />
     *                    - string|int|\Acme\Entity\Domain <code><b>primaryDomain</b></code> the primary domain of the certificate [optional if domains is not empty]<br />
     *                    - string|string[]|int|int[]|\Acme\Entity\Domain[] <code><b>domains</b></code> the domains of the certificate (if a string, separate domains with spaces or commas) [optional if domains is not empty]<br />
     *                    - int|string|null <code><b>privateKeyBits</b></code> the number of bits of the private key to be created [optional]
     * @param \ArrayAccess $errors Errors will be added here
     *
     * @return \Acme\Entity\Certificate|null NULL in case of errors
     */
    public function create(Account $account, array $data, ArrayAccess $errors)
    {
        $normalizedCreateData = $this->normalizeCreateData($account, $data, $errors);
        if ($normalizedCreateData === null) {
            return null;
        }
        $certificate = Certificate::create($account);
        $this->applyNormalizedCreateData($certificate, $normalizedCreateData);
        $this->em->persist($certificate);
        $this->em->flush($certificate);

        return $certificate;
    }

    /**
     * Edit an existing Certificate instance.
     *
     * @param \Acme\Entity\Certificate $certificate
     * @param array $data Keys:<br />
     *                    - string|int|\Acme\Entity\Domain <code><b>primaryDomain</b></code> the primary domain of the certificate [optional]<br />
     *                    - string|string[]|int|int[]|\Acme\Entity\Domain[] <code><b>domains</b></code> the domains of the certificate (if a string, separate domains with spaces or commas) [optional]<br />
     *                    - string|string[]|int|int[]|\Acme\Entity\Domain[] <code><b>addDomains</b></code> alter the existing domain list - don't use with the 'domains' option [optional]<br />
     *                    - string|string[]|int|int[]|\Acme\Entity\Domain[] <code><b>removeDomains</b></code> alter the existing domain list - don't use with the 'domains' option [optional]<br />
     *                    - bool|string|int <code><b>disabled</b></code> alter the existing disabled state [optional]<br />
     * @param \ArrayAccess $errors Errors will be added here
     *
     * @return bool FALSE in case of errors
     */
    public function edit(Certificate $certificate, array $data, ArrayAccess $errors)
    {
        $normalizedEditData = $this->normalizeEditData($data, $errors, $certificate);
        if ($normalizedEditData === null) {
            return false;
        }
        $objectsToFlush = [$certificate];
        $this->applyNormalizedEditData($certificate, $normalizedEditData, $objectsToFlush);
        $this->em->transactional(function () use ($objectsToFlush) {
            foreach ($objectsToFlush as $objectToFlush) {
                $this->em->flush($objectToFlush);
            }
        });

        return true;
    }

    /**
     * Delete a Certificate instance.
     *
     * @param \Acme\Entity\Certificate $certificate
     * @param \ArrayAccess $errors
     *
     * @return bool FALSE in case of errors
     */
    public function delete(Certificate $certificate, ArrayAccess $errors)
    {
        $deletable = true;

        if ($deletable !== true) {
            return false;
        }

        $account = $certificate->getAccount();
        $certificateInfo = $certificate->getCertificateInfo();

        $this->em->transactional(function () use ($certificate) {
            $certificate->setLastActionExecuted(null);
            $this->em->flush($certificate);
            $qb = $this->em->createQueryBuilder();
            $qb
                ->update(RevokedCertificate::class, 'rc')
                ->set('rc.parentCertificate', ':null')
                ->where($qb->expr()->eq('rc.parentCertificate', ':parentCertificate'))
                ->setParameter('null', null)
                ->setParameter('parentCertificate', $certificate)
                ->getQuery()->execute();
            $this->em->clear(RevokedCertificate::class);
            $id = $certificate->getID();
            $this->em->detach($certificate);
            $certificate = $this->em->find(Certificate::class, $id);
            $this->em->remove($certificate);
            $this->em->flush($certificate);
        });
        if ($certificateInfo !== null) {
            $this->revoker->revokeCertificateInfo($account, $certificateInfo, null, true);
        }

        return true;
    }

    /**
     * Extract/normalize the data received when creating a new Certificate.
     *
     * @param \Acme\Entity\Account $account
     * @param array $data
     * @param \ArrayAccess $errors
     *
     * @return array|null Return NULL in case of errors
     */
    protected function normalizeCreateData(Account $account, array $data, ArrayAccess $errors)
    {
        $state = new DataState($data, $errors);
        $normalizedData = $this->extractDomainList($state, $account)
            + [
                'keyPair' => $this->extractKeyPair($state),
            ]
        ;

        $unknownKeys = $state->getRemainingKeys();
        if ($unknownKeys !== []) {
            $state->addError(t('Unrecognized keys detected:') . "\n- " . implode("\n- ", $unknownKeys));
        }

        return $state->isFailed() ? null : $normalizedData;
    }

    /**
     * Apply to a Certificate instance the data extracted from the normalizeCreateData() method.
     *
     * @param \Acme\Entity\Certificate $certificate
     * @param array $normalizedCreateData
     */
    protected function applyNormalizedCreateData(Certificate $certificate, array $normalizedCreateData)
    {
        $certificate->setKeyPair($normalizedCreateData['keyPair']);
        $domainList = $certificate->getDomains();
        $domainList->add(CertificateDomain::create($certificate, $normalizedCreateData['primaryDomain'])->setIsPrimary(true));
        foreach ($normalizedCreateData['otherDomains'] as $otherDomain) {
            $domainList->add(CertificateDomain::create($certificate, $otherDomain));
        }
    }

    /**
     * Extract/normalize the data received when editing an existing Certificate.
     *
     * @param array $data
     * @param \ArrayAccess $errors
     * @param \Acme\Entity\Certificate $certificate
     *
     * @return array|null Return NULL in case of errors
     */
    protected function normalizeEditData(array $data, ArrayAccess $errors, Certificate $certificate)
    {
        $state = new DataState($data, $errors);
        $normalizedData = [];
        if ($certificate->getCsr() !== '' && $certificate->getOngoingOrder() !== null) {
            if ($state->hasValue('primaryDomain') || $state->hasValue('domains') || $state->hasValue('addDomains') || $state->hasValue('removeDomains')) {
                $state->popValue('primaryDomain');
                $state->popValue('domains');
                $state->popValue('addDomains');
                $state->popValue('removeDomains');
                if ($certificate->getCsr() === '') {
                    $state->addError(t("It's not possible to change the list of domains since the certificate is active"));
                } else {
                    $state->addError(t("It's not possible to change the list of domains since there's a currently active authorization process"));
                }
            }
        } else {
            if ($state->hasValue('domains')) {
                $normalizedData += $this->extractDomainList($state, $certificate->getAccount());
            } else {
                $normalizedData += $this->getCurrentDomainList($certificate);
            }
            $this->extractRemoveDomains($state, $normalizedData, $certificate->getAccount());
            $this->extractPrimaryDomainForEdit($state, $normalizedData, $certificate->getAccount());
            $this->extractAddDomains($state, $normalizedData, $certificate->getAccount());
            if ($normalizedData['primaryDomain'] === null) {
                $state->addError(t('The resulting list of the domains would be empty'));
            }
        }
        if ($state->hasValue('disabled')) {
            $normalizedData += ['disabled' => $this->booleanParser->toBoolean($state->popValue('disabled'))];
        }

        $unknownKeys = $state->getRemainingKeys();
        if ($unknownKeys !== []) {
            $state->addError(t('Unrecognized keys detected:') . "\n- " . implode("\n- ", $unknownKeys));
        }

        return $state->isFailed() ? null : $normalizedData;
    }

    /**
     * Apply to a Certificate instance the data extracted from the normalizeEditData() method.
     *
     * @param \Acme\Entity\Certificate $certificate
     * @param array $normalizedCreateData
     * @param array $changedEntities [output]
     */
    protected function applyNormalizedEditData(Certificate $certificate, array $normalizedCreateData, array &$changedEntities)
    {
        if (isset($normalizedCreateData['primaryDomain']) || isset($normalizedCreateData['otherDomains'])) {
            $domainList = $certificate->getDomains();
            $currentCertificateDomains = $domainList->toArray();
            foreach ($currentCertificateDomains as $currentCertificateDomain) {
                if ($normalizedCreateData['primaryDomain'] === $currentCertificateDomain->getDomain()) {
                    $currentCertificateDomain->setIsPrimary(true);
                    $changedEntities[] = $currentCertificateDomain;
                    $normalizedCreateData['primaryDomain'] = null;
                    continue;
                }
                $index = array_search($currentCertificateDomain->getDomain(), $normalizedCreateData['otherDomains'], true);
                if ($index === false) {
                    $domainList->removeElement($currentCertificateDomain);
                } else {
                    $changedEntities[] = $currentCertificateDomain;
                    $currentCertificateDomain->setIsPrimary(false);
                    array_splice($normalizedCreateData['otherDomains'], $index, 1);
                }
            }
            if ($normalizedCreateData['primaryDomain'] !== null) {
                $domainList->add(CertificateDomain::create($certificate, $normalizedCreateData['primaryDomain'])->setIsPrimary(true));
            }
            foreach ($normalizedCreateData['otherDomains'] as $otherDomain) {
                $domainList->add(CertificateDomain::create($certificate, $otherDomain));
            }
        }
        if (isset($normalizedCreateData['disabled'])) {
            $certificate->setDisabled($normalizedCreateData['disabled']);
        }
    }

    /**
     * Extract 'primaryDomain' and 'domains', checking that they are valid.
     *
     * @param \Acme\Editor\DataState $state
     * @param \Acme\Entity\Account $account
     *
     * @return array
     */
    protected function extractDomainList(DataState $state, Account $account)
    {
        $result = [
            'primaryDomain' => null,
            'otherDomains' => [],
        ];
        $value = $state->popValue('primaryDomain');
        if ($value) {
            try {
                $result['primaryDomain'] = $this->finder->findDomain($value, $account);
            } catch (EntityNotFoundException $x) {
                $state->addError($x->getMessage());
            }
        }

        foreach ($this->parseDomainList($state, $state->popValue('domains'), $account) as $domain) {
            if ($domain !== $result['primaryDomain']) {
                $result['otherDomains'][] = $domain;
            }
        }
        if ($result['primaryDomain'] === null) {
            $result['primaryDomain'] = array_shift($result['otherDomains']) ?: null;
            if ($state->isFailed() === false && $result['primaryDomain'] === null) {
                $state->addError(t('No domains have been specified'));
            }
        }

        return $result;
    }

    /**
     * Extract 'primaryDomain' and 'domains' from an existing Certificate entity.
     *
     * @param \Acme\Entity\Certificate $certificate
     *
     * @return array
     */
    protected function getCurrentDomainList(Certificate $certificate)
    {
        $result = [
            'primaryDomain' => null,
            'otherDomains' => [],
        ];
        foreach ($certificate->getDomains() as $certificateDomain) {
            if ($certificateDomain->isPrimary() && $result['primaryDomain'] === null) {
                $result['primaryDomain'] = $certificateDomain->getDomain();
            } else {
                $result['otherDomains'][] = $certificateDomain->getDomain();
            }
        }

        return $result;
    }

    /**
     * Extract 'removeDomains'.
     *
     * @param \Acme\Editor\DataState $state
     * @param array $normalizedData
     * @param \Acme\Entity\Account $account
     */
    protected function extractRemoveDomains(DataState $state, array &$normalizedData, Account $account)
    {
        foreach ($this->parseDomainList($state, $state->popValue('removeDomains'), $account) as $domain) {
            if ($normalizedData['primaryDomain'] === $domain) {
                $normalizedData['primaryDomain'] = null;
            } else {
                $index = array_search($domain, $normalizedData['otherDomains'], true);
                if ($index !== false) {
                    array_splice($normalizedData['otherDomains'], $index, 1);
                }
            }
        }
        if ($normalizedData['primaryDomain'] === null) {
            $normalizedData['primaryDomain'] = array_shift($normalizedData['otherDomains']) ?: null;
        }
    }

    /**
     * Extract 'primaryDomain' when editing a certificate.
     *
     * @param \Acme\Editor\DataState $state
     * @param array $normalizedData
     * @param \Acme\Entity\Account $account
     */
    protected function extractPrimaryDomainForEdit(DataState $state, array &$normalizedData, Account $account)
    {
        $value = $state->popValue('primaryDomain');
        if ($value === null || $value === '') {
            return;
        }
        try {
            $domain = $this->finder->findDomain($value, $account);
        } catch (EntityNotFoundException $x) {
            $state->addError($x->getMessage());

            return;
        }
        if ($normalizedData['primaryDomain'] !== null) {
            array_unshift($normalizedData['otherDomains'], $normalizedData['primaryDomain']);
        }
        $normalizedData['primaryDomain'] = $domain;
        $index = array_search($domain, $normalizedData['otherDomains'], true);
        if ($index !== false) {
            array_splice($normalizedData['otherDomains'], $index, 1);
        }
    }

    /**
     * Extract 'addDomains'.
     *
     * @param \Acme\Editor\DataState $state
     * @param array $normalizedData
     * @param \Acme\Entity\Account $account
     */
    protected function extractAddDomains(DataState $state, array &$normalizedData, Account $account)
    {
        foreach ($this->parseDomainList($state, $state->popValue('addDomains'), $account) as $domain) {
            if ($normalizedData['primaryDomain'] !== $domain) {
                if (!in_array($domain, $normalizedData['otherDomains'], true)) {
                    $normalizedData['otherDomains'][] = $domain;
                }
            }
        }
    }

    /**
     * Extract 'privateKeyBits', and create a new private/public key pair (if no previous errors occurred, so that we don't waste time).
     *
     * @param \Acme\Editor\DataState $state
     *
     * @return \Acme\Security\KeyPair|null
     */
    protected function extractKeyPair(DataState $state)
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

    /**
     * @param \Acme\Editor\DataState $state
     * @param string|string[]|int|int[]|\Acme\Entity\Domain|\Acme\Entity\Domain[] $valueList
     * @param \Acme\Entity\Account $account
     *
     * @return \Acme\Entity\Domain[]
     */
    protected function parseDomainList(DataState $state, $valueList, Account $account)
    {
        $domains = [];
        if (!is_array($valueList)) {
            if (is_object($valueList)) {
                $valueList = [$valueList];
            } else {
                $valueList = preg_split('/[ ,]/', (string) $valueList, -1, PREG_SPLIT_NO_EMPTY);
            }
        }
        foreach ($valueList as $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            try {
                $domain = $this->finder->findDomain($value, $account);
            } catch (EntityNotFoundException $x) {
                $state->addError($x->getMessage());
                continue;
            }
            if (!in_array($domain, $domains, true)) {
                $domains[] = $domain;
            }
        }

        return $domains;
    }
}
