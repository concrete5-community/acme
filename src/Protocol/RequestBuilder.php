<?php

namespace Acme\Protocol;

use Acme\Entity\Account;
use Acme\Exception\Codec\Exception as CodecException;
use Acme\Exception\KeyPair\SigningException;
use Acme\Exception\RuntimeException;
use Acme\Exception\UnrecognizedProtocolVersionException;
use Acme\Security\Crypto;
use phpseclib\Crypt\RSA;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class to build the body of calls to ACME servers.
 */
class RequestBuilder
{
    /**
     * @var \Acme\Protocol\NonceManager
     */
    protected $nonceManager;

    /**
     * @var \Acme\Security\Crypto
     */
    protected $crypto;

    /**
     * @param \Acme\Protocol\NonceManager $nonceManager
     * @param Crypto $crypto
     */
    public function __construct(NonceManager $nonceManager, Crypto $crypto)
    {
        $this->nonceManager = $nonceManager;
        $this->crypto = $crypto;
    }

    /**
     * Build the raw body of an HTTP request to be sent to an ACME server.
     *
     * @param \Acme\Entity\Account $account the account on the ACME server
     * @param string $url the URL to be called
     * @param array|null $payload the data to be sent to the ACME server (NULL for POST-as-GET Requests)
     *
     * @throws \Acme\Exception\Exception
     *
     * @return string
     */
    public function buildBody(Account $account, $url, array $payload = null)
    {
        $rsa = $this->getRSA($account);
        $header = $this->buildHeader($account, $rsa);
        $protected = $this->buildProtected($account, $url, $header);
        $signature = $this->sign($rsa, $protected, $payload);
        $result = [
            'protected' => $this->crypto->toJsonBase64($protected),
            'payload' => $payload === null ? '' : $this->crypto->toJsonBase64($payload),
            'signature' => $this->crypto->toBase64($signature),
        ];
        switch ($account->getServer()->getProtocolVersion()) {
            case Version::ACME_01:
                $result['header'] = $header;
                if ($payload !== null && isset($payload['resource'])) {
                    $result['resource'] = $payload['resource'];
                }
                break;
        }

        return $this->crypto->toJson($result);
    }

    /**
     * Get an RSA instance associated to the private key of an ACME account.
     *
     * @param \Acme\Entity\Account $account
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \phpseclib\Crypt\RSA
     */
    protected function getRSA(Account $account)
    {
        $privateKey = $account->getPrivateKey();
        if ($privateKey === '') {
            throw new RuntimeException(t('The ACME account does not contain a private key.'));
        }
        $rsa = new RSA();
        if ($rsa->loadKey($privateKey) === false) {
            throw new RuntimeException(t('The ACME account has an invalid private key.'));
        }
        if ($rsa->getPrivateKey() === false) {
            throw new RuntimeException(t('The ACME account has an invalid private key.'));
        }
        if ($rsa->sLen === null) {
            $rsa->sLen = false;
        }
        $rsa->setHash('sha256');
        $rsa->setMGFHash('sha256');
        $rsa->setSignatureMode(RSA::SIGNATURE_PKCS1);

        return $rsa;
    }

    /**
     * Build the header section of the request body.
     *
     * @param \Acme\Entity\Account $account
     * @param \phpseclib\Crypt\RSA $rsa the account private key
     *
     * @return array|null
     */
    protected function buildHeader(Account $account, RSA $rsa)
    {
        $common = [
            'alg' => 'RS256',
        ];
        if ($account->getServer()->getProtocolVersion() !== Version::ACME_01 && $account->getRegistrationURI() !== '') {
            return $common;
        }

        return $common + ['jwk' => $this->crypto->getJwk($rsa)];
    }

    /**
     * Build the protected section of the request body.
     *
     * @param \Acme\Entity\Account $account
     * @param string $url the URL to be called
     * @param array $header the header built by the buildHeader() method
     *
     * @return array
     */
    protected function buildProtected(Account $account, $url, array $header)
    {
        $server = $account->getServer();
        $nonce = $this->nonceManager->getNonceForRequest($server);
        $protected = $header + ['nonce' => $nonce];
        switch ($server->getProtocolVersion()) {
            case Version::ACME_01:
                break;
            case Version::ACME_02:
                $protected += [
                    'url' => $url,
                ];
                if ($account->getRegistrationURI() !== '') {
                    $protected += [
                        'kid' => $account->getRegistrationURI(),
                    ];
                }
                break;
            default:
                throw UnrecognizedProtocolVersionException::create($server->getProtocolVersion());
        }

        return $protected;
    }

    /**
     * Generate the signature for the protected + payload data.
     *
     * @param \phpseclib\Crypt\RSA $rsa the account private key
     * @param array $protected
     * @param array|null $payload
     *
     * @throws \Acme\Exception\KeyPair\SigningException
     *
     * @return string
     */
    protected function sign(RSA $rsa, array $protected, array $payload = null)
    {
        try {
            $toBeSigned = $this->crypto->toJsonBase64($protected) . '.' . ($payload === null ? '' : $this->crypto->toJsonBase64($payload));
        } catch (CodecException $x) {
            throw SigningException::create(t('Failed to build the data to be signed: %s', $x->getMessage()));
        }
        $signature = $rsa->sign($toBeSigned);
        if ($signature === false) {
            throw SigningException::create(t('Failed to sign the data to be sent to the ACME server'));
        }

        return $signature;
    }
}
