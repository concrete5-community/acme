<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var string $defaultManagementAddress
 * @var string $managementAddress
 * @var string $fieldsPrefix
 * @var Concrete\Core\Form\Service\Form $formService
 */

?>
<p><?= t('This is an authorization type that should be used in TEST ENVIRONMENTS only.') ?></p>

<div class="form-group">
    <?= $formService->label($fieldsPrefix . '[managementaddress]', t('URL of the HTTP management interface')) ?>
    <?= $formService->url($fieldsPrefix . '[managementaddress]', $managementAddress) ?>
    <div class="small text-muted">
        <?= t('Default: %s', '<code>' . h($defaultManagementAddress) . '</code>') ?>
    </div>
</div>
