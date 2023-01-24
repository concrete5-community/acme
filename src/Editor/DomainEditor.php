<?php

namespace Acme\Editor;

use Acme\ChallengeType\ChallengeTypeManager;
use Acme\Entity\Account;
use Acme\Entity\Certificate;
use Acme\Entity\Domain;
use ArrayAccess;
use Doctrine\ORM\EntityManagerInterface;
use MLocati\IDNA\DomainName;
use MLocati\IDNA\Exception\InvalidDomainNameCharacters;
use MLocati\IDNA\Exception\InvalidPunycode;
use MLocati\IDNA\Exception\InvalidString;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class to create/edit/delete Domain entities.
 */
final class DomainEditor
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    /**
     * @var \Acme\ChallengeType\ChallengeTypeManager
     */
    private $challengeTypeManager;

    public function __construct(EntityManagerInterface $em, ChallengeTypeManager $challengeTypeManager)
    {
        $this->em = $em;
        $this->challengeTypeManager = $challengeTypeManager;
    }

    /**
     * Create a new Domain instance.
     *
     * @param \Acme\Entity\Account $account the associated Account
     * @param array $data Keys:<br />
     *                    - string <code><b>hostname</b></code> the host name (or its punycode) of the domain [required]<br />
     *                    - string <code><b>challengetype</b></code> the handle of the challenge type [required]<br />
     *                    - any other options supported by the specific challenge type
     * @param \ArrayAccess $errors Errors will be added here
     * @param array $data
     *
     * @return \Acme\Entity\Domain|null NULL in case of errors
     */
    public function create(Account $account, array $data, ArrayAccess $errors)
    {
        $normalizedData = $this->normalizeData($data, $errors, null, $account);
        if ($normalizedData === null) {
            return null;
        }
        $domain = Domain::create($account);
        $this->applyNormalizedData($domain, $normalizedData);
        $this->em->persist($domain);
        $this->em->flush($domain);

        return $domain;
    }

    /**
     * Edit an existing Domain instance.
     *
     * @param array $data Keys:<br />
     *                    - string <code><b>hostname</b></code> the host name (or its punycode) of the domain [required]<br />
     *                    - string <code><b>challengetype</b></code> the handle of the challenge type [required]<br />
     *                    - any other options supported by the specific challenge type
     * @param \ArrayAccess $errors Errors will be added here
     *
     * @return bool FALSE in case of errors
     */
    public function edit(Domain $domain, array $data, ArrayAccess $errors)
    {
        $normalizedData = $this->normalizeData($data, $errors, $domain);
        if ($normalizedData === null) {
            return false;
        }
        $this->applyNormalizedData($domain, $normalizedData);
        $this->em->flush($domain);

        return true;
    }

    /**
     * Delete a Domain instance.
     *
     * @param \ArrayAccess $errors Errors will be added here
     *
     * @return bool FALSE in case of errors
     */
    public function delete(Domain $domain, ArrayAccess $errors)
    {
        $deletable = true;

        $numCertificates = $domain->getCertificates()->count();
        if ($numCertificates > 0) {
            $errors[] = t2(
                "It's not possible to delete the domain since it's associated to %s certificate",
                "It's not possible to delete the domain since it's associated to %s certificates",
                $numCertificates
            );
            $deletable = false;
        }

        if ($deletable !== true) {
            return false;
        }

        $this->em->remove($domain);
        $this->em->flush($domain);

        return true;
    }

    /**
     * Extract/normalize the data received.
     *
     * @param \Acme\Entity\Domain|null $domain NULL if (and only if) creating a new domain
     * @param \Acme\Entity\Account|null $account NULL if (and only if) editing an existing domain
     *
     * @return array|null Return NULL in case of errors
     */
    private function normalizeData(array $data, ArrayAccess $errors, Domain $domain = null, Account $account = null)
    {
        $state = new DataState($data, $errors);
        $normalizedData = $this->extractHostname($state, $domain, $account) + $this->extractChallengeType($state, $domain);

        $unknownKeys = $state->getRemainingKeys();
        if ($unknownKeys !== []) {
            $state->addError(t('Unrecognized keys detected:') . "\n- " . implode("\n- ", $unknownKeys));
        }
        if ($state->isFailed() === false) {
            if ($domain !== null) {
                $this->checkDomainNameChange($state, $normalizedData, $domain);
            }
            if ($state->isFailed() === false) {
                $this->checkChallengeType($state, $normalizedData, $domain, $account);
            }
        }

        return $state->isFailed() ? null : $normalizedData;
    }

    /**
     * Extract 'hostname', checking that it's valid and that's not already used.
     *
     * @param \Acme\Entity\Domain|null $domain NULL if (and only if) creating a new domain
     * @param \Acme\Entity\Account $account NULL if (and only if) editing an existing domain
     *
     * @return array
     */
    private function extractHostname(DataState $state, Domain $domain = null, Account $account = null)
    {
        $result = [
            'hostname' => '',
            'punycode' => '',
            'isWildcard' => false,
        ];
        $value = $state->popValue('hostname');
        $value = is_string($value) ? trim($value) : '';
        $value = trim($value, '.');
        $value = preg_replace('/\.\.+/', '.', $value);
        if ($value === '' || $value === '*') {
            $state->addError(t('Missing the host name of the domain'));

            return $result;
        }
        if (strpos($value, '*.') === 0) {
            $result['isWildcard'] = true;
            $result['hostname'] = substr($value, 2);
        } else {
            $result['hostname'] = $value;
        }
        if ($result['hostname'] === (string) (int) $result['hostname']) {
            $state->addError(t("The domain name can't be an integer"));

            return $result;
        }
        if (filter_var($result['hostname'], FILTER_VALIDATE_IP) !== false) {
            $state->addError(t('Please specify the domain name, not its IP address'));

            return $result;
        }
        try {
            if (preg_match('/^[\x21-\x7F]+$/', $result['hostname'])) {
                $domainName = DomainName::fromPunycode($result['hostname']);
            } else {
                $domainName = DomainName::fromName($result['hostname']);
            }
            $result['punycode'] = $domainName->getPunycode();
            if ($domainName->getName() !== $result['hostname']) {
                $state->addError(t("The host name '%1\$s' should be written as '%2\$s'", $result['hostname'], $domainName->getName()));

                return $result;
            }
        } catch (InvalidString $x) {
            $state->addError(t('%s is not a valid UTF-8 string', $result['hostname']));

            return $result;
        } catch (InvalidDomainNameCharacters $x) {
            $chars = (string) $x->getCharacters();
            if ($chars === '') {
                $state->addError(t('The host name contains invalid characters'));
            } else {
                $state->addError(t('The host name contains these invalid characters: %s', $chars));
            }

            return $result;
        } catch (InvalidPunycode $x) {
            $state->addError(t('The host name is not valid'));

            return $result;
        }
        if ($domain !== null) {
            $numCertificates = $domain->getCertificates()->count();
            if ($numCertificates !== 0) {
                if ($domain->getPunycode() !== $result['punycode'] || $domain->isWildcard() !== $result['isWildcard']) {
                    $state->addError(t2(
                        "It's not possible to change the domain name since it's used in %s certificate",
                        "It's not possible to change the domain name since it's used in %s certificates",
                        $numCertificates
                    ));
                }
            }
        }

        if ($account === null) {
            $account = $domain->getAccount();
        }
        $qb = $this->em->createQueryBuilder();
        $existing = $qb
            ->from(Domain::class, 'd')
            ->select('d.id')
            ->where($qb->expr()->orX(
                $qb->expr()->eq('d.hostname', ':hostname'),
                $qb->expr()->eq('d.punycode', ':punycode')
            ))
            ->andWhere($qb->expr()->eq('d.isWildcard', ':isWildcard'))
            ->andWhere($qb->expr()->eq('d.account', ':account'))
            ->setParameter('hostname', $result['hostname'])
            ->setParameter('punycode', $result['punycode'])
            ->setParameter('isWildcard', $result['isWildcard'])
            ->setParameter('account', $account->getID())
            ->getQuery()->getScalarResult()
        ;
        if ($existing !== [] && ($domain === null || (int) $existing[0]['id'] !== $domain->getID())) {
            $state->addError(t(
                'The domain %1$s already exists for the account %2$s',
                $result['isWildcard'] ? "*.{$result['hostname']}" : $result['hostname'],
                $account->getName()
            ));
        }

        return $result;
    }

    /**
     * Extract 'challengetype', plus any other options supported by the specific challenge type.
     *
     * @param \Acme\Entity\Domain|null $domain NULL if (and only if) creating a new domain
     *
     * @return array
     */
    private function extractChallengeType(DataState $state, Domain $domain = null)
    {
        $result = [
            'challengeType' => null,
            'challengeTypeConfiguration' => [],
        ];
        $challengeTypeHandle = $state->popValue('challengetype');
        $challengeTypeHandle = is_string($challengeTypeHandle) ? trim($challengeTypeHandle) : '';
        if ($challengeTypeHandle === '') {
            $state->addError(t('Missing the domain authorization type'));

            return $result;
        }

        $challengeType = $this->challengeTypeManager->getChallengeByHandle($challengeTypeHandle);
        if ($challengeType === null) {
            $state->addError(t('The "%s" domain authorization type is invalid', $challengeTypeHandle));

            return $result;
        }
        $result['challengeType'] = $challengeType;
        $defaultConfiguration = [];
        $domainConfiguration = $domainConfiguration = $domain === null || $domain->getChallengeTypeHandle() !== $challengeType->getHandle() ? [] : $domain->getChallengeTypeConfiguration();
        foreach ($challengeType->getConfigurationDefinition() as $key => $data) {
            if (empty($data['derived'])) {
                $defaultConfiguration[$key] = $data['defaultValue'];
            } else {
                unset($domainConfiguration[$key]);
            }
        }
        foreach ($defaultConfiguration as $key => $defaultValue) {
            $result['challengeTypeConfiguration'][$key] = $state->popValue($key, array_get($domainConfiguration, $key, $defaultValue));
        }

        return $result;
    }

    private function checkDomainNameChange(DataState $state, array $normalizedData, Domain $domain)
    {
        if (true
            && $domain->getHostname() === $normalizedData['hostname']
            && $domain->getPunycode() === $normalizedData['punycode']
            && $domain->isWildcard() === $normalizedData['isWildcard']
        ) {
            return;
        }
        $qb = $this->em->createQueryBuilder();
        $activeCertificates = (int) $qb
            ->select($qb->expr()->count('c.id'))
            ->from(Certificate::class, 'c')
            ->innerJoin('c.domains', 'cd')
            ->where($qb->expr()->neq('c.csr', ':emptyString'))->setParameter('emptyString', '')
            ->andWhere($qb->expr()->eq('cd.domain', ':domain'))->setParameter('domain', $domain)
            ->getQuery()->getSingleScalarResult()
        ;
        if ($activeCertificates > 0) {
            $state->addError(t2(
                "It's not possible to change the host name of the domain since it's associated to %s active certificate",
                "It's not possible to change the host name of the domain since it's associated to %s active certificates",
                $activeCertificates
            ));
        }
    }

    /**
     * @param \Acme\Entity\Domain|null $domain NULL if (and only if) creating a new domain
     * @param \Acme\Entity\Account|null $account NULL if (and only if) editing an existing domain
     */
    private function checkChallengeType(DataState $state, array &$normalizedData, Domain $domain = null, Account $account = null)
    {
        $challengeType = $normalizedData['challengeType'];
        /** @var \Acme\ChallengeType\ChallengeTypeInterface $challengeType */
        if ($domain !== null && $domain->getChallengeTypeHandle() === $challengeType->getHandle()) {
            $previousChallengeConfiguration = $domain->getChallengeTypeConfiguration();
        } else {
            $previousChallengeConfiguration = [];
        }
        $testDomain = Domain::create($account === null ? $domain->getAccount() : $account);
        $this->applyNormalizedData($testDomain, $normalizedData);
        $challengeTypeConfiguration = $challengeType->checkConfiguration($testDomain, $normalizedData['challengeTypeConfiguration'], $previousChallengeConfiguration, $state->getErrors());
        if ($challengeTypeConfiguration === null) {
            $state->setFailed();
        } else {
            $normalizedData['challengeTypeConfiguration'] = $challengeTypeConfiguration;
        }
    }

    /**
     * Apply to a Domain instance the data extracted from the normalizeData() method.
     */
    private function applyNormalizedData(Domain $domain, array $normalizedData)
    {
        $domain
            ->setHostname($normalizedData['hostname'])
            ->setPunycode($normalizedData['punycode'])
            ->setIsWildcard($normalizedData['isWildcard'])
            ->setChallengeType($normalizedData['challengeType'], $normalizedData['challengeTypeConfiguration'])
        ;
    }
}
