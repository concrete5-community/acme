<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var bool $apiTokenConfigured
 * @var string $fieldsPrefix
 * @var Concrete\Core\Form\Service\Form $formService
 */

?>
<div class="form-group">
    <?= $formService->label($fieldsPrefix . '[apitoken]', t('Personal Access Token')) ?>
    <?php
    if ($apiTokenConfigured) {
        echo $formService->password($fieldsPrefix . '[apiToken]', '', ['placeholder' => t('Keep the previous value')]);
    } else {
        echo $formService->password($fieldsPrefix . '[apiToken]', '', ['required' => 'required', 'placeholder' => t('Example: %s', 'dop_v1_...')]);
    }
    ?>
    <div class="small text-muted">
        <?= t(
            'You can generate API tokens %shere%s.',
            '<a href="https://cloud.digitalocean.com/account/api/tokens" target="_blank" rel="noreferrer noopener">',
            '</a>'
        ) ?>
    </div>
</div>
