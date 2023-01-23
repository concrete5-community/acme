<?php

namespace Acme\Install;

use Acme\Crypto\Engine;
use Acme\Exception\RuntimeException;
use Acme\Http\ClientFactory;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Foundation\Environment\FunctionInspector;
use phpseclib\Crypt\RSA as RSA2;
use phpseclib\Math\BigInteger as BigInteger2;
use phpseclib3\Crypt\Common\AsymmetricKey;
use phpseclib3\Math\BigInteger as BigInteger3;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class that provides tests to be performed before the package is installed.
 */
final class Checks
{
    /**
     * FTP extension state: unavailable.
     *
     * @var int
     */
    const FTPEXTENSION_UNAVAILABLE = 0;

    /**
     * FTP extension state: available (but without SSL support).
     *
     * @var int
     */
    const FTPEXTENSION_NOSSL = 1;

    /**
     * FTP extension state: available.
     *
     * @var int
     */
    const FTPEXTENSION_OK = 2;

    /**
     * @var \Acme\Http\ClientFactory
     */
    private $clientFactory;

    /**
     * @var \Concrete\Core\Foundation\Environment\FunctionInspector
     */
    private $functionInspector;

    /**
     * @var \Concrete\Core\Config\Repository\Repository
     */
    private $config;

    /**
     * @var int
     */
    private $engineID;

    /**
     * Is the OpenSSL extension available?
     *
     * @var bool|null NULL if not yet initialized
     */
    private $openSslInstalled;

    /**
     * Errors in OpenSSL configuration.
     *
     * @var string|null NULL if not yet initialized, empty string if OK
     */
    private $openSslMisconfigurationProblems;

    /**
     * Do we have a fast big-integer library available?
     *
     * @var bool|null NULL if not yet initialized
     */
    private $fastBigIntegerAvailable;

    /**
     * Errors detected when using the HTTP client for secure connections.
     *
     * @var string|null NULL if not yet initialized, empty string if OK
     */
    private $httpClientError;

    /**
     * The state of the FTP extension.
     *
     * @var int|null One of the FTPEXTENSION_... constants (NULL if not yet initialized)
     */
    private $ftpExtensionState;

    /**
     * The output of the phpinfo() function.
     *
     * @var string|null NULL if not yet initialized
     */
    private $phpInfoOutput;

    /**
     * Flag to remember if we already initialized phpseclib.
     *
     * @var bool|null NULL if not yet initialized
     */
    private $phpSecLibUseOpenSSL;

    /**
     * @param int|null $engineID The value of one of the Acme\Crypto\Engine constants
     */
    public function __construct(ClientFactory $clientFactory, FunctionInspector $functionInspector, Repository $config, $engineID = null)
    {
        $this->clientFactory = $clientFactory;
        $this->functionInspector = $functionInspector;
        $this->config = $config;
        $this->engineID = $engineID === null ? Engine::get() : $engineID;
    }

    /**
     * Check if there's some unment requirement.
     *
     * @return bool
     */
    public function isSomeRequirementMissing()
    {
        return false
            || $this->isFastBigIntegerAvailable() !== true
            || $this->isHttpClientWorking() !== true
        ;
    }

    /**
     * Is the OpenSSL extension available?
     *
     * @return bool
     */
    public function isOpenSslInstalled()
    {
        if ($this->openSslInstalled === null) {
            $this->openSslInstalled = extension_loaded('openssl');
        }

        return $this->openSslInstalled;
    }

    /**
     * Is the OpenSSL extension misconfigured?
     *
     * @return bool
     */
    public function isOpenSslMisconfigured()
    {
        return $this->getOpenSslMisconfigurationProblems() !== '';
    }

    /**
     * Get the reason why OpenSSL is misconfigured.
     *
     * @return string empty string if no problems
     */
    public function getOpenSslMisconfigurationProblems()
    {
        if ($this->openSslMisconfigurationProblems === null) {
            if ($this->isOpenSslInstalled()) {
                if ($this->phpSecLibUseOpenSSL() === false) {
                    $this->openSslMisconfigurationProblems = $this->detectOpenSslMisconfigurationProblems($this->getPhpInfoOutput());
                } else {
                    $this->openSslMisconfigurationProblems = '';
                }
            } else {
                $this->openSslMisconfigurationProblems = '';
            }
        }

        return $this->openSslMisconfigurationProblems;
    }

    /**
     * Do we have a fast big-integer library available?
     *
     * @return bool
     */
    public function isFastBigIntegerAvailable()
    {
        if ($this->fastBigIntegerAvailable === null) {
            switch ($this->engineID) {
                case Engine::PHPSECLIB2:
                    if (!defined('MATH_BIGINTEGER_MODE')) {
                        new BigInteger2();
                    }
                    $this->fastBigIntegerAvailable = MATH_BIGINTEGER_MODE !== BigInteger2::MODE_INTERNAL;
                    break;
                case Engine::PHPSECLIB3:
                    $engine = BigInteger3::getEngine();
                    $this->fastBigIntegerAvailable = !in_array($engine[0], ['PHP32', 'PHP64'], true) || !in_array($engine[1], ['DefaultEngine'], true);
                    break;
                default:
                    $this->fastBigIntegerAvailable = false;
                    break;
            }
        }

        return $this->fastBigIntegerAvailable;
    }

    /**
     * Is the HTTP client working with HTTPS connections?
     */
    public function isHttpClientWorking()
    {
        return $this->getHttpClientError() === '';
    }

    /**
     * Errors detected when using the HTTP client for secure connections.
     *
     * @return string empty string if no problems
     */
    public function getHttpClientError()
    {
        if ($this->httpClientError === null) {
            $httpClientError = '';
            $sampleServerList = $this->config->get('acme::sample_servers');
            if (is_array($sampleServerList)) {
                foreach ($sampleServerList as $sampleServers) {
                    if (is_array($sampleServers)) {
                        foreach ($sampleServers as $sampleServer) {
                            if (!is_array($sampleServer)) {
                                continue;
                            }
                            if (!empty($sampleServer['allowUnsafeConnections'])) {
                                continue;
                            }
                            $directoryUrl = array_get($sampleServer, 'directoryUrl');
                            if (!$directoryUrl) {
                                continue;
                            }
                            $httpClient = $this->clientFactory->getClient();
                            try {
                                $response = $httpClient->head($directoryUrl);
                                if ($response->statusCode === 200) {
                                    $httpClientError = '';
                                    break 2;
                                }
                                $httpClientError === "Error {$response->statusCode} ({$response->reasonPhrase})";
                            } catch (RuntimeException $x) {
                                $httpClientError = $x->getMessage();
                            }
                        }
                    }
                }
            }
            $this->httpClientError = $httpClientError;
        }

        return $this->httpClientError;
    }

    /**
     * Get the state of the FTP extension.
     *
     * @return int One of the FTPEXTENSION_... constants
     */
    public function getFtpExtensionState()
    {
        if ($this->ftpExtensionState === null) {
            if ($this->functionInspector->functionAvailable('ftp_login') && $this->functionInspector->functionAvailable('ftp_connect')) {
                if ($this->functionInspector->functionAvailable('ftp_ssl_connect')) {
                    $this->ftpExtensionState = static::FTPEXTENSION_OK;
                } else {
                    $this->ftpExtensionState = static::FTPEXTENSION_NOSSL;
                }
            } else {
                $this->ftpExtensionState = static::FTPEXTENSION_UNAVAILABLE;
            }
        }

        return $this->ftpExtensionState;
    }

    /**
     * Get the output of the phpinfo() function.
     *
     * @return string
     */
    private function getPhpInfoOutput()
    {
        if ($this->phpInfoOutput === null) {
            ob_start();
            phpinfo();
            $this->phpInfoOutput = ob_get_clean();
        }

        return $this->phpInfoOutput;
    }

    /**
     * Detect problems in OpenSSL.
     *
     * @param string $phpInfo the output of the phpinfo() function
     *
     * @return string
     */
    private function detectOpenSslMisconfigurationProblems($phpInfo)
    {
        $m = $matches = null;
        preg_match_all('#OpenSSL (Header|Library) Version(.*)#im', $phpInfo, $matches);
        $versions = [];
        if (!empty($matches[1])) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                $fullVersion = trim(str_replace('=>', '', strip_tags($matches[2][$i])));
                // Remove letter part in OpenSSL version
                if (!preg_match('/(\d+\.\d+\.\d+)/i', $fullVersion, $m)) {
                    $versions[$matches[1][$i]] = $fullVersion;
                } else {
                    $versions[$matches[1][$i]] = $m[0];
                }
            }
        }
        if (isset($versions['Header'])) {
            if (isset($versions['Library'])) {
                return t('The version of the OpenSSL Header (%1$s) and the version of the OpenSSL Library (%2$s) are different', $versions['Header'], $versions['Library']);
            }

            return t('The version of the OpenSSL Header has been found (%s), but the version of the OpenSSL Library has not been detected', $versions['Header']);
        }
        if (isset($versions['Library'])) {
            return t('The version of the OpenSSL Library has been found (%s), but the version of the OpenSSL Header has not been detected', $versions['Library']);
        }

        return t('It was not possible to determine the OpenSSL version');
    }

    /**
     * @return bool
     */
    private function phpSecLibUseOpenSSL()
    {
        if ($this->phpSecLibUseOpenSSL === null) {
            switch ($this->engineID) {
                case Engine::PHPSECLIB2:
                    if (!defined('CRYPT_RSA_MODE')) {
                        new RSA2();
                    }
                    $this->phpSecLibUseOpenSSL = CRYPT_RSA_MODE === RSA2::MODE_OPENSSL;
                    break;
                case Engine::PHPSECLIB3:
                    $engines = AsymmetricKey::useBestEngine();
                    $this->phpSecLibUseOpenSSL = !empty($engines['OpenSSL']);
                    break;
                default:
                    $this->phpSecLibUseOpenSSL = false;
                    break;
            }
        }

        return $this->phpSecLibUseOpenSSL;
    }
}
