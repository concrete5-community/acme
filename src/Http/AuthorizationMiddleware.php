<?php

namespace Acme\Http;

use Acme\Entity\AuthorizationChallenge;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Http\Middleware\DelegateInterface;
use Concrete\Core\Http\Middleware\MiddlewareInterface;
use Concrete\Core\Http\ResponseFactoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Parameter;
use Symfony\Component\HttpFoundation\Request;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Middleware that intercepts authorization calls from an ACME server.
 */
final class AuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * The prefix of authorization calls.
     *
     * @var string
     */
    const ACME_CHALLENGE_PREFIX = '/.well-known/acme-challenge/';

    /**
     * A request token to be used when testing authorization calls.
     *
     * @var string
     */
    const ACME_CHALLENGE_TOKEN_TESTINTERCEPT = 'testIntercept';

    /**
     * @var \Concrete\Core\Http\ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var \Concrete\Core\Config\Repository\Repository
     */
    private $config;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $em;

    public function __construct(ResponseFactoryInterface $responseFactory, Repository $config, EntityManagerInterface $em)
    {
        $this->responseFactory = $responseFactory;
        $this->config = $config;
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Http\Middleware\MiddlewareInterface::process()
     */
    public function process(Request $request, DelegateInterface $frame)
    {
        $challengeToken = $this->getAuthorizationChallengeToken($request);
        if ($challengeToken !== '') {
            $qb = $this->em->createQueryBuilder();
            $qb
                ->select('ac.challengeAuthorizationKey')
                ->from(AuthorizationChallenge::class, 'ac')
                ->andWhere($qb->expr()->eq('ac.challengePrepared', ':true'))
                ->andWhere($qb->expr()->eq('ac.challengeToken', ':challengeToken'))
                ->setMaxResults(1)
                ->setParameters(new ArrayCollection([
                    new Parameter('true', true),
                    new Parameter('challengeToken', $challengeToken),
                ]))
            ;
            $rows = $qb->getQuery()->getScalarResult();
            if ($rows !== []) {
                return $this->buildChallengeAuthorizationKeyResponse($rows[0]['challengeAuthorizationKey']);
            }
            if ($challengeToken === static::ACME_CHALLENGE_TOKEN_TESTINTERCEPT) {
                return $this->buildChallengeAuthorizationKeyResponse(sha1($this->config->get('acme::site.unique_installation_id')));
            }
        }

        return $frame->next($request);
    }

    /**
     * @return string
     */
    private function getAuthorizationChallengeToken(Request $request)
    {
        $pathInfo = $request->getRequestUri();
        if (strpos($pathInfo, static::ACME_CHALLENGE_PREFIX) !== 0) {
            return '';
        }
        $challengeToken = substr($pathInfo, strlen(static::ACME_CHALLENGE_PREFIX));
        if ($challengeToken === '' || $challengeToken[0] === '?') {
            return '';
        }
        $p = strpos($challengeToken, '?');
        if ($p !== false) {
            $challengeToken = substr($challengeToken, 0, $p);
        }

        return $challengeToken;
    }

    /**
     * @param string $challengeAuthorizationKey
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function buildChallengeAuthorizationKeyResponse($challengeAuthorizationKey)
    {
        return $this->responseFactory->create(
            $challengeAuthorizationKey,
            200,
            [
                'Content-Type' => 'application/octet-stream',
            ]
        );
    }
}
