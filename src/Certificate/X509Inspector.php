<?php

namespace Acme\Certificate;

use Acme\Crypto\Engine;
use Acme\Exception\Exception;
use Acme\Exception\NotImplementedException;
use Acme\Exception\RuntimeException;
use Acme\Service\DateTimeParser;
use phpseclib\File\X509 as X5092;
use phpseclib3\File\X509 as X5093;
use Throwable;

final class X509Inspector
{
    /**
     * @var \phpseclib\File\X509|\phpseclib3\File\X509
     */
    private $value;

    /**
     * @var int
     */
    private $engineID;

    /**
     * @param \phpseclib\File\X509|\phpseclib3\File\X509 $value
     * @param int $engineID
     */
    private function __construct($value, $engineID)
    {
        $this->value = $value;
        $this->engineID = $engineID;
    }

    /**
     * @param string|mixed $value
     * @param int|null $engineID The value of one of the Acme\Crypto\Engine constants
     *
     * @throws \Acme\Exception\RuntimeException
     *
     * @return self
     */
    public static function fromString($value, $engineID = null)
    {
        if (!is_string($value) || $value === '') {
            throw new RuntimeException(t('The certificate is empty'));
        }
        if ($engineID === null) {
            $engineID = Engine::get();
        }
        switch ($engineID) {
            case Engine::PHPSECLIB2:
                $x509 = new X5092();
                if ($x509->loadX509($value) !== false) {
                    return new self($x509, $engineID);
                }
                throw new RuntimeException(t('Failed to load the certificate'));
            case Engine::PHPSECLIB3:
                $x509 = new X5093();
                try {
                    if ($x509->loadX509($value) !== false) {
                        return new self($x509, $engineID);
                    }
                } catch (Throwable $x) {
                }
                throw new RuntimeException(t('Failed to load the certificate'));
            default:
                throw new NotImplementedException();
        }
    }

    /**
     * @throws \Acme\Exception\Exception
     *
     * @return \DateTime
     */
    public function extractStartDate(DateTimeParser $dateTimeParser)
    {
        $value = $this->extractDate(['tbsCertificate.validity.notBefore.generalTime', 'tbsCertificate.validity.notBefore.utcTime'], $dateTimeParser);
        if ($value !== null) {
            return $value;
        }
        throw new RuntimeException(t('Failed to determine the initial validity of the certificate'));
    }

    /**
     * @throws \Acme\Exception\Exception
     *
     * @return \DateTime
     */
    public function extractEndDate(DateTimeParser $dateTimeParser)
    {
        $value = $this->extractDate(['tbsCertificate.validity.notAfter.generalTime', 'tbsCertificate.validity.notAfter.utcTime'], $dateTimeParser);
        if ($value !== null) {
            return $value;
        }
        throw new RuntimeException(t('Failed to determine the final validity of the certificate'));
    }

    /**
     * @throws \Acme\Exception\Exception
     *
     * @return string
     */
    public function extractOcspResponderUrl()
    {
        $methods = $this->getExtensionValue('id-pe-authorityInfoAccess');
        if (!is_array($methods)) {
            return '';
        }
        foreach ($methods as $method) {
            if (is_array($method) && array_get($method, 'accessMethod') === 'id-ad-ocsp') {
                $accessLocation = array_get($method, 'accessLocation');
                if (is_array($accessLocation)) {
                    $url = array_get($accessLocation, 'uniformResourceIdentifier');
                    if (is_string($url)) {
                        return $url;
                    }
                }
            }
        }

        return '';
    }

    /**
     * @return string[]
     */
    public function extractNames()
    {
        $result = [];
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                $commonName = $this->value->getDNProp('id-at-commonName');
                $name = is_array($commonName) ? array_shift($commonName) : $commonName;
                if (is_string($name) && $name !== '') {
                    $result[] = $name;
                }
                break;
            default:
                throw new NotImplementedException();
        }
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
            case Engine::PHPSECLIB3:
                $altNames = $this->value->getExtension('id-ce-subjectAltName');
                if (is_array($altNames)) {
                    foreach ($altNames as $altName) {
                        if (!is_array($altName)) {
                            continue;
                        }
                        foreach ($altName as $altNameType => $altNameValue) {
                            switch (is_string($altNameType) ? $altNameType : '') {
                                case 'dNSName':
                                    if (is_string($altNameValue) && $altNameValue !== '') {
                                        if (!in_array($altNameValue, $result, true)) {
                                            $result[] = $altNameValue;
                                        }
                                    }
                                    break;
                            }
                        }
                    }
                }
                break;
            default:
                throw new NotImplementedException();
        }

        return $result;
    }

    /**
     * @param string[] $keys
     *
     * @throws \Acme\Exception\Exception
     *
     * @return \DateTime|null
     */
    private function extractDate(array $keys, DateTimeParser $dateTimeParser)
    {
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
                $currentCert = $this->value->currentCert;
                break;
            case Engine::PHPSECLIB3:
                $currentCert = $this->value->getCurrentCert();
                break;
            default:
                throw new NotImplementedException();
        }
        foreach ($keys as $key) {
            try {
                $value = $dateTimeParser->toDateTime(array_get($currentCert, $key));
                if ($value !== null) {
                    return $value;
                }
            } catch (Exception $x) {
            }
        }

        return null;
    }

    /**
     * @param string $extensionId
     *
     * @return mixed|null
     */
    private function getExtensionValue($extensionId)
    {
        switch ($this->engineID) {
            case Engine::PHPSECLIB2:
                $currentCert = $this->value->currentCert;
                break;
            case Engine::PHPSECLIB3:
                $currentCert = $this->value->getCurrentCert();
                break;
            default:
                throw new NotImplementedException();
        }
        $extensions = array_get($currentCert, 'tbsCertificate.extensions');
        if (is_array($extensions)) {
            foreach ($extensions as $x) {
                if (is_array($x) && array_get($x, 'extnId') === $extensionId) {
                    return array_get($x, 'extnValue');
                }
            }
        }

        return null;
    }
}
