<?php

namespace Acme\Certificate;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Options for the Renewer::nextStep() method.
 */
class RenewerOptions
{
    /**
     * Should the certificate be renewed also if not needed?
     *
     * @var bool
     */
    protected $forceCertificateRenewal;

    /**
     * Should the certificate actions be executed also if not needed?
     *
     * @var bool
     */
    protected $forceActionsExecution;

    /**
     * Should we check if the certificate has been revoked?
     *
     * @var bool
     */
    protected $checkRevocation;

    protected function __construct()
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

        return $result
            ->setForceCertificateRenewal(false)
            ->setForceActionsExecution(false)
            ->setCheckRevocation(false)
        ;
    }

    /**
     * Should the certificate be renewed also if not needed?
     *
     * @return bool
     */
    public function isForceCertificateRenewal()
    {
        return $this->forceCertificateRenewal;
    }

    /**
     * Should the certificate be renewed also if not needed?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setForceCertificateRenewal($value)
    {
        $this->forceCertificateRenewal = (bool) $value;

        return $this;
    }

    /**
     * Should the certificate actions be executed also if not needed?
     *
     * @return bool
     */
    public function isForceActionsExecution()
    {
        return $this->forceActionsExecution;
    }

    /**
     * Should the certificate actions be executed also if not needed?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setForceActionsExecution($value)
    {
        $this->forceActionsExecution = (bool) $value;

        return $this;
    }

    /**
     * Should we check if the certificate has been revoked?
     *
     * @return bool
     */
    public function isCheckRevocation()
    {
        return $this->checkRevocation;
    }

    /**
     * Should we check if the certificate has been revoked?
     *
     * @param bool $value
     *
     * @return $this
     */
    public function setCheckRevocation($value)
    {
        $this->checkRevocation = (bool) $value;

        return $this;
    }
}
