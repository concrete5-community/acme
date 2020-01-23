<?php

if (!is_file('/proc/self/cgroup') || !preg_match('%^\d+:\w+:/(docker|actions_job)/' . preg_quote(gethostname(), '%') . '\w+%sm', file_get_contents('/proc/self/cgroup'))) {
    throw new RuntimeException('Must be running in docker.');
}

define('DIR_BASE', '/app');

chdir(DIR_BASE);

if (strpos(file_get_contents('/app/application/bootstrap/app.php'), '$console->setAutoExit(false);') === false) {
    file_put_contents('/app/application/bootstrap/app.php', <<<'EOT'

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
