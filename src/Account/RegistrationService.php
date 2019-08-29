<?php

namespace Acme\Account;

use Acme\Entity\Account;
use Acme\Exception\UnrecognizedProtocolVersionException;
use Acme\Protocol\Communicator;
use Acme\Protocol\Version;
use DateTime;

defined('C5_EXECUTE') or die('Access Denied.');

class RegistrationService
{
    /**
     * @var \Acme\Protocol\Communicator
     */
    protected $communicator;

    /**
     * @param \Acme\Protocol\Communicator $communicator
     */
    public function __construct(Communicator $communicator)
    {
        $this->communicator = $communicator;
    }

    /**
     * Register an account.
     *
     * @param \Acme\Entity\Account $account
     * @param string $acceptedTermsOfService the URL of the accepted terms of service (see the getTermsOfServiceUrl() method of the Server instance)
     * @param bool $allowExistingAccount
     *
     * @throws \Acme\Exception\UnrecognizedProtocolVersionException when the ACME Protocol version is not recognized
     * @throws \Acme\Exception\Exception
     */
    public function registerAccount(Account $account, $acceptedTermsOfService = '', $allowExistingAccount = false)
    {
        $response = $this->communicator->send(
            $account,
            'POST',
            $account->getServer()->getNewAccountUrl(),
            $this->getRegistrationPayload($account, $acceptedTermsOfService),
            $allowExistingAccount ? [200, 201, 409] : [200, 201]
        );
        $account
            ->setRegistrationURI($response->getLocation())
            ->setRegisteredOn(new DateTime())
        ;
    }

    /**
     * Get the payload to be sent to the ACME server when registering an account.
     *
     * @param \Acme\Entity\Account $account the account to be registered
     * @param string $acceptedTermsOfService the URL of the accepted terms of service (see the getTermsOfServiceUrl() method of the Server instance)
     *
     * @throws \Acme\Exception\UnrecognizedProtocolVersionException when the ACME Protocol version is not recognized
     *
     * @return array
     */
    protected function getRegistrationPayload(Account $account, $acceptedTermsOfService = '')
    {
        $server = $account->getServer();
        if ($server->getTermsOfServiceUrl() === '') {
            $termsOfServiceAccepted = null;
        } else {
            $termsOfServiceAccepted = $acceptedTermsOfService === $server->getTermsOfServiceUrl();
        }
        $result = [
            'contact' => [
                'mailto:' . $account->getEmail(),
            ],
        ];
        switch ($server->getProtocolVersion()) {
            case Version::ACME_01:
                $result += ['resource' => 'new-reg'];
                if ($termsOfServiceAccepted !== null) {
                    $result += ['agreement' => $termsOfServiceAccepted ? $server->getTermsOfServiceUrl() : ''];
                }
                break;
            case Version::ACME_02:
                if ($termsOfServiceAccepted !== null) {
                    $result += ['termsOfServiceAgreed' => $termsOfServiceAccepted];
                }
                break;
            default:
                throw UnrecognizedProtocolVersionException::create($server->getProtocolVersion());
        }

        return $result;
    }
}
