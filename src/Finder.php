<?php

namespace Acme;

use Acme\Entity\Account;
use Acme\Entity\Domain;
use Acme\Entity\RemoteServer;
use Acme\Entity\Server;
use Acme\Exception\EntityNotFoundException;
use Concrete\Core\Database\Query\LikeBuilder;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class Finder
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $em;

    /**
     * @var \Concrete\Core\Database\Query\LikeBuilder
     */
    protected $likeBuilder;

    /**
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @param \Concrete\Core\Database\Query\LikeBuilder $likeBuilder
     */
    public function __construct(EntityManagerInterface $em, LikeBuilder $likeBuilder)
    {
        $this->em = $em;
        $this->likeBuilder = $likeBuilder;
    }

    /**
     * Find a server given its ID, name, or initial part of the name.
     *
     * @param \Acme\Entity\Server|string|int|mixed $criteria
     *
     * @throws \Acme\Exception\EntityNotFoundException
     *
     * @return \Acme\Entity\Server
     */
    public function findServer($criteria)
    {
        if ($criteria instanceof Server) {
            return $criteria;
        }
        if ($this->isInteger($criteria)) {
            $server = $this->em->find(Server::class, (int) $criteria);
            if ($server !== null) {
                return $server;
            }
            throw new EntityNotFoundException(t("There's no ACME server with ID %s", $criteria));
        }
        if (!is_string($criteria)) {
            throw new EntityNotFoundException(t('Invalid search criteria variable type: %s', gettype($criteria)));
        }
        $repo = $this->em->getRepository(Server::class);
        $server = $repo->findOneBy(['name' => $criteria]);
        if ($server !== null) {
            return $server;
        }
        $servers = $this->findByInitialName(Server::class, $criteria);
        $numServers = count($servers);
        switch ($numServers) {
            case 0:
                throw new EntityNotFoundException(t("There's no ACME server with name '%s'", $criteria));
            case 1:
                return array_pop($servers);
            default:
                throw new EntityNotFoundException(t("More than one ACME server (exactly %1\$s) was found with a name starting with '%2\$s'. Please be more specific.", $numServers, $criteria));
        }
    }

    /**
     * Find an account given its ID, name, or initial part of the name.
     *
     * @param \Acme\Entity\Server|string|int|mixed $criteria
     *
     * @throws \Acme\Exception\EntityNotFoundException
     *
     * @return \Acme\Entity\Account
     */
    public function findAccount($criteria)
    {
        if ($criteria instanceof Account) {
            return $criteria;
        }
        if ($this->isInteger($criteria)) {
            $account = $this->em->find(Account::class, (int) $criteria);
            if ($account !== null) {
                return $account;
            }
            throw new EntityNotFoundException(t("There's no ACME account with ID %s", $criteria));
        }
        if (!is_string($criteria)) {
            throw new EntityNotFoundException(t('Invalid search criteria variable type: %s', gettype($criteria)));
        }
        $repo = $this->em->getRepository(Account::class);
        $account = $repo->findOneBy(['name' => $criteria]);
        if ($account !== null) {
            return $account;
        }
        $accounts = $this->findByInitialName(Account::class, $criteria);
        $numAccounts = count($accounts);
        switch ($numAccounts) {
            case 0:
                throw new EntityNotFoundException(t("There's no ACME account with name '%s'", $criteria));
            case 1:
                return array_pop($accounts);
            default:
                throw new EntityNotFoundException(t("More than one ACME account (exactly %1\$s) was found with a name starting with '%2\$s'. Please be more specific.", $numAccounts, $criteria));
        }
    }

    /**
     * Find a domain given its ID or host name.
     *
     * @param \Acme\Entity\Domain|string|int|mixed $criteria
     * @param \Acme\Entity\Account|null limit the search to a specific account
     * @param Account|null $account
     *
     * @throws \Acme\Exception\EntityNotFoundException
     *
     * @return \Acme\Entity\Domain
     */
    public function findDomain($criteria, Account $account = null)
    {
        if ($criteria instanceof Domain) {
            if ($account === null || $criteria->getAccount() === $account) {
                return $criteria;
            }
            throw new EntityNotFoundException(t("The domain '%1\$s' is associated to the account '%2\$s' and not to the account '%3\$s'", $criteria->getHostDisplayName(), $criteria->getAccount()->getName(), $account->getName()));
        }
        if ($this->isInteger($criteria)) {
            $domain = $this->em->find(Domain::class, (int) $criteria);
            if ($domain !== null) {
                if ($account === null || $domain->getAccount() === $account) {
                    return $domain;
                }
                throw new EntityNotFoundException(t("The domain with ID %1\$s is associated to the account '%2\$s' and not to the account '%3\$s'", $criteria, $domain->getAccount()->getName(), $account->getName()));
            }
            throw new EntityNotFoundException(t("There's no domain with ID %s", $criteria));
        }
        if (!is_string($criteria)) {
            throw new EntityNotFoundException(t('Invalid search criteria variable type: %s', gettype($criteria)));
        }
        if (strpos($criteria, '*.') === 0) {
            $isWildcard = true;
            $hostname = substr($criteria, 2);
        } else {
            $isWildcard = false;
            $hostname = $criteria;
        }
        $repo = $this->em->getRepository(Domain::class);
        $where = ['isWildcard' => $isWildcard];
        if ($account !== null) {
            $where = ['account' => $account];
        }
        $domains = array_unique(
            array_merge(
                $repo->findBy($where + ['hostname' => $hostname]),
                $repo->findBy($where + ['punycode' => $hostname])
            ),
            SORT_REGULAR
        );
        $numDomains = count($domains);
        switch ($numDomains) {
            case 0:
                if ($account === null) {
                    throw new EntityNotFoundException(t("There's no domain with host name '%s'", $criteria));
                }
                throw new EntityNotFoundException(t("There's no domain with host name '%1\$s' for the ACME account '%2\$s'", $criteria, $account->getName()));
            case 1:
                return array_pop($domains);
            default:
                throw new EntityNotFoundException(t("More than one domain (exactly %1\$s) was found with the host name '%2\$s'. Please specify the account.", $numDomains, $criteria));
        }
    }

    /**
     * Find a remote server given its ID, name, or initial part of the name.
     *
     * @param \Acme\Entity\RemoteServer|string|int|mixed $criteria
     *
     * @throws \Acme\Exception\EntityNotFoundException
     *
     * @return \Acme\Entity\RemoteServer
     */
    public function findRemoteServer($criteria)
    {
        if ($criteria instanceof RemoteServer) {
            return $criteria;
        }
        if ($this->isInteger($criteria)) {
            $remoteServer = $this->em->find(RemoteServer::class, (int) $criteria);
            if ($remoteServer !== null) {
                return $remoteServer;
            }
            throw new EntityNotFoundException(t("There's no remote server with ID %s", $criteria));
        }
        if (!is_string($criteria)) {
            throw new EntityNotFoundException(t('Invalid search criteria variable type: %s', gettype($criteria)));
        }
        $repo = $this->em->getRepository(RemoteServer::class);
        $remoteServer = $repo->findOneBy(['name' => $criteria]);
        if ($remoteServer !== null) {
            return $remoteServer;
        }
        $remoteServers = $this->findByInitialName(RemoteServer::class, $criteria);
        $numRemoteServers = count($remoteServers);
        switch ($numRemoteServers) {
            case 0:
                throw new EntityNotFoundException(t("There's no remote server with name '%s'", $criteria));
            case 1:
                return array_pop($remoteServers);
            default:
                throw new EntityNotFoundException(t("More than one remote server (exactly %1\$s) was found with a name starting with '%2\$s'. Please be more specific.", $numRemoteServers, $criteria));
        }
    }

    /**
     * Find entities whose name starts with a string.
     *
     * @param string $class
     * @param string $criteria
     *
     * @return object[]
     */
    protected function findByInitialName($class, $criteria)
    {
        $qb = $this->em->createQueryBuilder();

        return $qb
            ->from($class, 'e')
            ->select('e')
            ->where($qb->expr()->like('e.name', ':nameLike'))
            ->setParameter('nameLike', $this->likeBuilder->escapeForLike($criteria, false))
            ->getQuery()->getResult();
    }

    /**
     * Check if a value is an integer, or a string representing an integer.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function isInteger($value)
    {
        return is_int($value) || is_string($value) && $value === (string) (int) $value;
    }
}
