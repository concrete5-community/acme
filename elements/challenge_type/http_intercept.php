<?php

use Acme\Http\AuthorizationMiddleware;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var bool $nocheck
 * @var bool $isInstalledInWebroot
 * @var bool $isPrettyUrlEnabled
 * @var string $seoUrlsPage
 *
 * @var string $fieldsPrefix
 * @var Concrete\Core\Form\Service\Form $formService
 */

?>
<p><?= t('With this validation, the ACME server reads an URL located under the root of the website using the HTTP protocol.') ?></p>
<p><?= t('concrete5 will intercept the calls made to <strong>this</strong> server, no file will be written to the webroot directory.') ?></p>
<?= t('Requirements:') ?>
<ol>
    <li>
        <?= t('this concrete5 instance is not installed in a sub-directory') ?>
        <?= $isInstalledInWebroot ? '<span class="label label-success">' . t('ok') . '</span>' : '<span class="label label-danger">' . t('NO!') . '</span>' ?>
    </li>
    <li>
        <?= t('<a href="%s" target="_blank">pretty URLs</a> are enabled', h($seoUrlsPage)) ?>
        <?= $isPrettyUrlEnabled ? '<span class="label label-success">' . t('ok') . '</span>' : '<span class="label label-danger">' . t('NO!') . '</span>' ?>
    </li>
    <li>
        <?= t('PHP and webserver are configured to pass URLs that start with %s to concrete5', '<code>' . h(AuthorizationMiddleware::ACME_CHALLENGE_PREFIX) . '</code>') ?>
    </li>
</ol>

<div class="form-group">
    <?= $formService->label($fieldsPrefix . '[nocheck]', t('Check the configuration when saving')) ?>
    <?= $formService->select(
        $fieldsPrefix . '[nocheck]',
        ['1' => t('No'), '0' => t('Yes')],
        $nocheck ? '1' : '0'
    ) ?>
</div>
