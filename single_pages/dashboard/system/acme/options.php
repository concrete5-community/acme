<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var int $renewDaysBeforeExpiration
 * @var int $minimumKeySize
 * @var int $defaultKeySize
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Page\View\PageView $view
 */
?>
<form method="POST" action="<?= h($view->action('submit')) ?>">
    <?php $token->output('acme-options') ?>

    <div class="form-group">
        <?= $form->label('renewDaysBeforeExpiration', t('Allow renewing certificates')) ?>
        <div class="input-group">
            <?= $form->number('renewDaysBeforeExpiration', $renewDaysBeforeExpiration, ['required' => 'required', 'min' => 1]) ?>
            <span class="input-group-addon"><?= t('days before expiration') ?></span>
        </div>
    </div>

    <div class="form-group">
        <?= $form->label('defaultKeySize', t('Default size of private keys')) ?>
        <div class="input-group">
            <?= $form->number('defaultKeySize', $defaultKeySize, ['required' => 'required', 'min' => $minimumKeySize]) ?>
            <span class="input-group-addon"><?= Punic\Unit::getName('digital/bit', 'long') ?></span>
        </div>
    </div>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <div class="pull-right">
                <input type="submit" class="btn btn-primary ccm-input-submit" value="<?= t('Save') ?>">
            </div>
        </div>
    </div>

</form>
