<?php

namespace Acme\Certificate;

use Acme\Entity\Order;
use Acme\Log\ArrayLogger;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Class that contains the result of the Renewer::nextStep() method.
 */
final class RenewState extends ArrayLogger
{
    /**
     * The number of seconds to wait before re-calling the the "nextStep()" after this number of seconds (NULL if no).
     *
     * @var int|null
     */
    private $nextStepAfter;

    /**
     * The certificate order/authorizations request.
     *
     * @var \Acme\Entity\Order|null
     */
    private $orderOrAuthorizationsRequest;

    /**
     * The info about the new issued certificate (if any).
     *
     * @var \Acme\Certificate\CertificateInfo|null
     */
    private $newCertificateInfo;

    private function __construct()
    {
    }

    /**
     * Create a new instance.
     *
     * @return static
     */
    public static function create()
    {
        $result = new static();

        return $result;
    }

    /**
     * Get the number of seconds to wait before re-calling the the "nextStep()" after this number of seconds (NULL if there's no other step).
     *
     * @return int|null
     */
    public function getNextStepAfter()
    {
        return $this->nextStepAfter;
    }

    /**
     * Set the number of seconds to wait before re-calling the the "nextStep()" after this number of seconds (NULL if no).
     *
     * @param int|null $value
     *
     * @return $this
     */
    public function setNextStepAfter($value)
    {
        $this->nextStepAfter = (string) $value === '' ? null : (int) $value;

        return $this;
    }

    /**
     * Set the number of seconds to wait at least before re-calling the the "nextStep()" after this number of seconds (NULL if no).
     *
     * @param int|null $value
     *
     * @return $this
     */
    public function setNextStepAtLeastAfter($value)
    {
        $current = $this->getNextStepAfter();
        if ($current === null || (string) $value === '') {
            return $this->setNextStepAfter($value);
        }
        $value = (int) $value;
        if ($current < $value) {
            return $this->setNextStepAfter($value);
        }
        return $this;
    }

    /**
     * Get the info about the new issued certificate (if any).
     *
     * @return \Acme\Certificate\CertificateInfo|null
     */
    public function getNewCertificateInfo()
    {
        return $this->newCertificateInfo;
    }

    /**
     * Set the certificate order/authorizations request.
     *
     * @return $this
     */
    public function setOrderOrAuthorizationsRequest(Order $value = null)
    {
        $this->orderOrAuthorizationsRequest = $value;

        return $this;
    }

    /**
     * Get the certificate order/authorizations request.
     *
     * @return \Acme\Entity\Order|null
     */
    public function getOrderOrAuthorizationsRequest()
    {
        return $this->orderOrAuthorizationsRequest;
    }

    /**
     * Set the info about the new issued certificate (if any).
     *
     * @return $this
     */
    public function setNewCertificateInfo(CertificateInfo $value = null)
    {
        $this->newCertificateInfo = $value;

        return $this;
    }
}
