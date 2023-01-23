<?php
if (empty($_ENV['ACME_ALLOW_TEST_OUTSIDE_DOCKER'])) {
    if (!is_file('/proc/self/cgroup')) {
        throw new RuntimeException("Must be running in docker (/proc/self/cgroup couldn't be found).");
    }
    $cgroup = file_get_contents('/proc/self/cgroup');
    if (!$cgroup) {
        throw new RuntimeException('Failed to read file /proc/self/cgroup');
    }
    if (trim($cgroup) !== '0::/') {
        $hostname = gethostname();
        if (!$hostname) {
            throw new RuntimeException('Failed to get hostname');
        }
        if (!preg_match('%^\d+:\w+:/(docker|actions_job)/' . preg_quote($hostname, '%') . '\w+%sm', $cgroup)) {
            throw new RuntimeException("Must be running in docker!\nThis doesn't seems the case from the contents of the /proc/self/cgroup file:\n{$cgroup}");
        }
        unset($hostname);
    }
    unset($cgroup);
}

define('DIR_BASE', str_replace(DIRECTORY_SEPARATOR, '/', realpath('../..')));

chdir(DIR_BASE);

if (strpos(file_get_contents(DIR_BASE . '/application/bootstrap/app.php'), '$console->setAutoExit(false);') === false) {
    file_put_contents(
        DIR_BASE . '/application/bootstrap/app.php',
        <<<'EOT'

if (isset($console)) {
    $console->setAutoExit(false);
}

EOT
        ,
        FILE_APPEND
    );
}

$backup = $_SERVER['argv'];
$_SERVER['argv'] = [$_SERVER['argv'][0], '--quiet', '--no-interaction'];
require_once DIR_BASE . '/concrete/dispatcher.php';
$_SERVER['argv'] = $backup;
unset($backup);
