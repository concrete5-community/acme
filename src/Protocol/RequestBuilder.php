<?php

namespace Acme\Protocol;

use Acme\Crypto\Engine;
use Acme\Crypto\PrivateKey;
use Acme\Entity\Account;
use Acme\Exception\Codec\Exception as CodecException;
use Acme\Exception\KeyPair\SigningException;
use Acme\Exception\RuntimeException;
use Acme\Exception\UnrecognizedProtocolVersionException;
use Acme\Service\Base64EncoderTrait;
use Acme\Service\JsonEncoderTrait;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class to build the body of calls to ACME servers.
 */
final class RequestBuilder
{
    use Base64EncoderTrait;

    use JsonEncoderTrait;

    /**
     * @var \Acme\Protocol\NonceManager
     */
    private $nonceManager;

    /**
     * @var int
     */
    private $engineID;

    /**
     * @param int|null $engineID The value of one of the Acme\Crypto\Engine constants
     */
    public function __construct(NonceManager $nonceManager, $engineID = null)
    {
        $this->nonceManager = $nonceManager;
        $this->engineID = $engineID === null ? Engine::get() : $engineID;
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
        $privateKey = $this->getPrivateKey($account);
        $header = $this->buildHeader($account, $privateKey);
        $protected = $this->buildProtected($account, $url, $header);
        $signature = $this->sign($privateKey, $protected, $payload);
        $result = [
            'protected' => $this->toJsonBase64UrlSafe($protected),
            'payload' => $payload === null ? '' : $this->toJsonBase64UrlSafe($payload),
            'signature' => $this->toBase64UrlSafe($signature),
        ];
        switch ($account->getServer()->getProtocolVersion()) {
            case Version::ACME_01:
                $result['header'] = $header;
                if ($payload !== null && isset($payload['resource'])) {
                    $result['resource'] = $payload['resource'];
                }
                break;
        }

        return $this->toJson($result);
    }

    /**
     * Get a private key instance associated to the private key of an ACME account.
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \Acme\Crypto\PrivateKey
     */
    private function getPrivateKey(Account $account)
    {
        $privateKeyString = $account->getPrivateKey();
        if ($privateKeyString === '') {
            throw new RuntimeException(t('The ACME account does not contain a private key.'));
        }
        try {
            $privateKey = PrivateKey::fromString($privateKeyString, $this->engineID);
        } catch (RuntimeException $x) {
            throw new RuntimeException(t('The ACME account has an invalid private key.'));
        }

        return $privateKey->prepareForSigningRequests();
    }

    /**
     * Build the header section of the request body.
     *
     * @param \Acme\Crypto\PrivateKey $privateKey the account private key
     *
     * @return array|null
     */
    private function buildHeader(Account $account, PrivateKey $privateKey)
    {
        $common = [
            'alg' => 'RS256',
        ];
        if ($account->getServer()->getProtocolVersion() !== Version::ACME_01 && $account->getRegistrationURI() !== '') {
            return $common;
        }

        return $common + ['jwk' => $privateKey->getJwk()];
    }

    /**
     * Build the protected section of the request body.
     *
     * @param string $url the URL to be called
     * @param array $header the header built by the buildHeader() method
     *
     * @return array
     */
    private function buildProtected(Account $account, $url, array $header)
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
     * @param \Acme\Crypto\PrivateKey $privateKey the account private key
     *
     * @throws \Acme\Exception\KeyPair\SigningException
     *
     * @return string
     */
    private function sign(PrivateKey $privateKey, array $protected, array $payload = null)
    {
        try {
            $toBeSigned = $this->toJsonBase64UrlSafe($protected) . '.' . ($payload === null ? '' : $this->toJsonBase64UrlSafe($payload));
        } catch (CodecException $x) {
            throw SigningException::create(t('Failed to build the data to be signed: %s', $x->getMessage()));
        }
        $signature = $privateKey->sign($toBeSigned);
        if ($signature === false) {
            throw SigningException::create(t('Failed to sign the data to be sent to the ACME server'));
        }

        return $signature;
    }

    /**
     * Render some data in JSON format, and encodes it in base64.
     *
     * @param array|string|mixed $data
     *
     * @throws \Acme\Exception\Codec\JsonEncodingException when we couldn't get the JSON represetation
     * @throws \Acme\Exception\Codec\Base64EncodingException when we couldn't convert to base-64
     *
     * @return string
     */
    private function toJsonBase64UrlSafe($data)
    {
        return $this->toBase64UrlSafe($this->toJson($data));
    }
}
