<?php

use Acme\Http\AuthorizationMiddleware;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var int[] $authorizationPorts
 * @var string $server
 * @var Acme\Entity\RemoteServer $remoteServers
 * @var string $webroot
 * @var bool $nocheck
 * @var string $remoteServersPage
 * @var string $fieldsPrefix
 * @var Concrete\Core\Form\Service\Form $formService
 */

$servers = [
    '.' => t('this server'),
];
foreach ($remoteServers as $remoteServer) {
    $servers[$remoteServer->getID()] = $remoteServer->getName();
}
?>

<p><?= t('With this validation, the ACME server reads an URL located under the root of the website using the HTTP protocol.') ?></p>
<p><?= t('With this method, a directory and a file will be physically created in the webroot directory, and ACME server will retrieve it automatically.') ?></p>
<?= t('Requirements:') ?>
<ol>
    <li>
        <?= t('PHP needs write access to the root directory of the webserver') ?>
    </li>
    <li>
        <?= t('your webserver is configured to serve URLs that start with %s', '<code>' . h(AuthorizationMiddleware::ACME_CHALLENGE_PREFIX) . '</code>') ?>
    </li>
</ol>

<div class="form-group">
    <?= $formService->label($fieldsPrefix . '[server]', t('Server containing the web root directory')) ?>
    <?= $formService->select($fieldsPrefix . '[server]', $servers, $server) ?>
    <div class="small text-muted">
        <?= t('You can define remote servers %shere%s.', '<a href="' . h($remoteServersPage) . '" target="_blank">', '</a>') ?>
    </div>
</div>

<div class="form-group">
    <?= $formService->label($fieldsPrefix . '[webroot]', t('Absolute path to the root directory of the website')) ?>
    <?= $formService->text($fieldsPrefix . '[webroot]', $webroot) ?>
</div>

<div class="form-group">
    <?= $formService->label($fieldsPrefix . '[nocheck]', t('Check the configuration when saving')) ?>
    <?= $formService->select(
        $fieldsPrefix . '[nocheck]',
        ['1' => t('No'), '0' => t('Yes')],
        $nocheck ? '1' : '0'
    ) ?>
</div>

<script>
$(document).ready(function() {
    var $server = $('select[name=<?= json_encode($fieldsPrefix . '[server]') ?>]'),
        $webroot = $('input[name=<?= json_encode($fieldsPrefix . '[webroot]') ?>]')
    ;
    $server
        .on('change', function() {
            if ($server.val() === '.') {
                $webroot.attr('placeholder', <?= json_encode(t('Leave empty to use the webroot of the current domain.')) ?>);
            } else {
                $webroot.removeAttr('placeholder');
            }
        })
        .trigger('change')
    ;
});
</script>