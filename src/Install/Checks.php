<?php

namespace Acme\Install;

use Acme\Http\ClientFactory;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Foundation\Environment\FunctionInspector;
use Exception;
use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Helper class that provides tests to be performed before the package is installed.
 */
class Checks
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
    protected $clientFactory;

    /**
     * @var \Concrete\Core\Foundation\Environment\FunctionInspector
     */
    protected $functionInspector;

    /**
     * @var \Concrete\Core\Config\Repository\Repository
     */
    protected $config;

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
     * @var bool
     */
    private $phpSecLibInitialized = false;

    /**
     * @param \Acme\Http\ClientFactory $clientFactory
     * @param \Concrete\Core\Foundation\Environment\FunctionInspector $functionInspector
     * @param \Concrete\Core\Config\Repository\Repository $config
     */
    public function __construct(ClientFactory $clientFactory, FunctionInspector $functionInspector, Repository $config)
    {
        $this->clientFactory = $clientFactory;
        $this->functionInspector = $functionInspector;
        $this->config = $config;
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
                // Initialize RSA, so that it defines its constants
                $this->initializePhpSecLib();
                if (CRYPT_RSA_MODE === RSA::MODE_INTERNAL) {
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
            $this->initializePhpSecLib();
            $this->fastBigIntegerAvailable = MATH_BIGINTEGER_MODE !== BigInteger::MODE_INTERNAL;
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
                            try {
                                $httpClient = $this->clientFactory->getClient();
                                $response = $httpClient->setMethod('HEAD')->setUri($directoryUrl)->send();
                                if ($response->isOk()) {
                                    $httpClientError = '';
                                    break 2;
                                }
                                $httpClientError = $httpClientError ?: $response->getReasonPhrase();
                            } catch (Exception $x) {
                                $httpClientError = $httpClientError ?: ($x->getMessage() ?: get_class($x));
                            } catch (Throwable $x) {
                                $httpClientError = $httpClientError ?: ($x->getMessage() ?: get_class($x));
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
    protected function getPhpInfoOutput()
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
    protected function detectOpenSslMisconfigurationProblems($phpInfo)
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
     * Initialize phpseclib.
     */
    protected function initializePhpSecLib()
    {
        if ($this->phpSecLibInitialized === true) {
            return;
        }
        new RSA();
        new BigInteger();
        $this->phpSecLibInitialized = true;
    }
}
