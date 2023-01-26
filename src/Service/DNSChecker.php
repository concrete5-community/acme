<?php

namespace Acme\Service;

use Concrete\Core\Foundation\Environment\FunctionInspector;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

defined('C5_EXECUTE') or die('Access Denied.');

final class DNSChecker
{
    /**
     * @var \Concrete\Core\Foundation\Environment\FunctionInspector
     */
    private $functionInspector;

    /**
     * @var bool|null
     */
    private $nslookupAvailable;

    private $resolverNameservers = [];

    public function __construct(FunctionInspector $functionInspector)
    {
        $this->functionInspector = $functionInspector;
    }

    /**
     * @param string $punycodeDomain
     * @param string $recordName
     * @param string|bool $nameserver TRUE to try to detect the authoritative nameserver and use it, false to always use the system lookup, a string containing a nameserver otherwise
     *
     * @return string[] Empty array in case of errors
     */
    public function listTXTRecords($punycodeDomain, $recordName = '', $nameserver = true, LoggerInterface $logger = null)
    {
        if ($logger === null) {
            $logger = new NullLogger();
        }
        switch (gettype($nameserver)) {
            case 'boolean':
                break;
            case 'string':
                if ($nameserver === '') {
                    $nameserver = true;
                }
                break;
            default:
                $nameserver = true;
                break;
        }
        $punycodeDomain = trim($punycodeDomain, '.');
        $recordName = trim($recordName, '.');
        $fullRecordName = $recordName === '' ? $punycodeDomain : "{$recordName}.{$punycodeDomain}";
        if ($nameserver !== false) {
            if (!$this->isNSLookupAvailable()) {
                $logger->debug(t('DNS requests will be made using the nameservers of the current system since the %s program is not available.', 'nslookup'));
                $nameserver = false;
            } elseif ($nameserver === true) {
                $nameserver = $this->resolveNameserver($punycodeDomain);
                if ($nameserver === '') {
                    $logger->debug(t("DNS requests will be made using the nameservers of the current system since we haven't been able to determine the authoritative nameservers."));
                    $nameserver = false;
                }
            }
        }
        if ($nameserver === false) {
            $logger->debug(t('Fetching DNS recurds using the nameservers of the current system', $nameserver));
            $records = $this->listTXTRecordsNative($fullRecordName, $logger);
        } else {
            $logger->debug(t('Fetching DNS recurds using the %s nameservers', $nameserver));
            $records = $this->listTXTRecordsNSLookup($fullRecordName, $nameserver);
        }
        if ($records === []) {
            $logger->debug(t('No DNS record has been found.'));
        } else {
            $logger->debug(t('DNS record found:') . "\n- " . implode("\n- ", $records));
        }

        return $records;
    }

    /**
     * @return bool
     */
    private function isNSLookupAvailable()
    {
        if ($this->nslookupAvailable === null) {
            $nslookupAvailable = false;
            if ($this->functionInspector->functionAvailable('exec')) {
                $rc = -1;
                $output = [];
                if (DIRECTORY_SEPARATOR === '\\') {
                    exec('nslookup.exe www.google.com 2>&1', $output, $rc);
                } else {
                    exec('nslookup www.google.com 2>&1', $output, $rc);
                }
                if ($rc === 0) {
                    $nslookupAvailable = true;
                }
            }
            $this->nslookupAvailable = $nslookupAvailable;
        }

        return $this->nslookupAvailable;
    }

    /**
     * @param string $punycodeDomain
     *
     * @return string Empty string in case of errors
     */
    private function resolveNameserver($punycodeDomain)
    {
        if (isset($this->resolverNameservers[$punycodeDomain])) {
            return $this->resolverNameservers[$punycodeDomain];
        }
        set_error_handler(static function () {}, -1);
        try {
            $records = dns_get_record($punycodeDomain, DNS_NS);
        } finally {
            restore_error_handler();
        }
        if (!is_array($records)) {
            return '';
        }
        $nameservers = [];
        foreach ($records as $record) {
            if (is_array($record)
                && isset($record['type']) && $record['type'] === 'NS'
                && array_key_exists('target', $record) && is_string($record['target']) && $record['target'] !== ''
            ) {
                $nameservers[] = $record['target'];
            }
        }
        if ($nameservers === []) {
            return '';
        }
        natcasesort($nameservers);
        $this->resolverNameservers[$punycodeDomain] = array_shift($nameservers);

        return $this->resolverNameservers[$punycodeDomain];
    }

    /**
     * @param string $fullRecordName
     *
     * @return string[] Empty array in case of errors
     */
    private function listTXTRecordsNative($fullRecordName)
    {
        set_error_handler(static function () {}, -1);
        try {
            $records = dns_get_record($fullRecordName, DNS_TXT);
        } finally {
            restore_error_handler();
        }
        if (!is_array($records)) {
            return [];
        }
        $result = [];
        foreach ($records as $record) {
            if (is_array($record)
                && isset($record['type']) && $record['type'] === 'TXT'
                && array_key_exists('txt', $record) && is_string($record['txt'])
            ) {
                $result[] = $record['txt'];
            }
        }

        return $result;
    }

    /**
     * @param string $fullRecordName
     * @param string $nameserver
     *
     * @return string[] Empty array in case of errors
     */
    private function listTXTRecordsNSLookup($fullRecordName, $nameserver)
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $cmd = 'nslookup.exe';
        } else {
            $cmd = 'nslookup';
        }
        $cmd .= ' -type=TXT ' . escapeshellarg($fullRecordName) . ' ' . escapeshellarg($nameserver) . ' 2>&1';
        $rc = -1;
        $output = [];
        exec($cmd, $output, $rc);
        if ($rc !== 0) {
            return [];
        }

        return DIRECTORY_SEPARATOR === '\\' ? $this->extractTXTRecordsNSLookupWin($output) : $this->extractTXTRecordsNSLookupPosix($output);
    }

    /**
     * @param string[] $lines
     *
     * @return string[]
     */
    private function extractTXTRecordsNSLookupWin(array $lines)
    {
        $lines = array_filter(
            array_map('trim', $lines),
            static function ($line) { return $line !== ''; }
        );
        $lines[] = '';
        $result = [];
        $current = false;
        $matches = null;
        foreach ($lines as $line) {
            if (preg_match('/^"(.*)"$/', $line, $matches)) {
                if ($current === true) {
                    $current = $matches[1];
                } elseif ($current !== false) {
                    $current .= $matches[1];
                }
                continue;
            }
            if ($current !== false) {
                if ($current !== true) {
                    $result[] = $current;
                }
                $current = false;
            }
            if (preg_match('/\stext\s+=$/', $line)) {
                $current = true;
            }
        }

        return $result;
    }

    /**
     * @param string[] $lines
     *
     * @return string[] Empty array in case of errors
     */
    private function extractTXTRecordsNSLookupPosix(array $lines)
    {
        $lines = array_filter(
            array_map('trim', $lines),
            static function ($line) { return $line !== ''; }
        );
        $result = [];
        $matches = null;
        foreach ($lines as $line) {
            if (preg_match('/^.*\stext\s+=\s+"(.*)"$/', $line, $matches)) {
                $result[] = $matches[1];
            }
        }

        return $result;
    }
}
