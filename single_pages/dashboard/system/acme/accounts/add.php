<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\Server $server
 * @var string $termsOfServiceUrl
 * @var int $defaultKeySize
 * @var int $minimumKeySize
 * @var bool $otherAccountsExist
 * @var Concrete\Core\User\User $currentUser
 * @var Concrete\Core\User\UserInfo $currentUserInfo
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 */
?>

<form method="POST" action="<?= h($view->action('submit', $server->getID())) ?>">

    <?php $token->output('acme-account-add-' . $server->getID()) ?>

    <div class="form-group">
        <?= $form->label('name', t('Name'), ['class' => 'launch-tooltip', 'title' => h(t('Give this account a name of your choice'))]) ?>
        <div class="input-group">
            <?= $form->text('name', $currentUser->getUserName(), ['required' => 'required', 'maxlength' => '190']) ?>
            <span class="input-group-addon"><i class="fa fa-asterisk"></i></span>
        </div>
    </div>

    <div class="form-group">
        <?= $form->label('email', t('Email')) ?>
        <div class="input-group">
            <?= $form->email('email', $currentUserInfo->getUserEmail(), ['required' => 'required']) ?>
            <span class="input-group-addon"><i class="fa fa-asterisk"></i></span>
        </div>
    </div>

    <div class="form-group">
        <?= $form->label('', t('Options')) ?>
        <?php
        if ($termsOfServiceUrl !== '') {
            ?>
            <div class="checkbox">
                <label>
                    <?= $form->checkbox('acceptedTermsOfService', h($termsOfServiceUrl), false, ['required' => 'required']) ?>
                    <?= t('I accept the <a href="%s" target="_blank">terms of service</a> of the ACME server', h($termsOfServiceUrl)) ?>
                </label>
            </div>
            <?php
        }
        if ($otherAccountsExist) {
            ?>
            <div class="checkbox">
                <label>
                    <?= $form->checkbox('default', '1') ?>
                    <?= t('Set as default account') ?>
                </label>
            </div>
            <?php
        }
        ?>
        <div class="checkbox">
            <label>
                <?= $form->checkbox('useExisting', '1') ?>
                <?= t('Use an existing account on the ACME server') ?>
            </label>
        </div>
    </div>

    <div class="form-group" style="display: none">
        <?= $form->label('privateKey', t('Private key of the existing user')) ?>
        <?= $form->textarea('privateKey', ['required' => 'required', 'rows' => '10', 'style' => 'resize: vertical; font-family: monospace']) ?>
    </div>

    <fieldset>
        <legend><?= t('Advanced options') ?></legend>
        <div class="form-group">
            <?= $form->label('privateKeyBits', t('Size of private key to create')) ?>
            <div class="input-group">
                <?= $form->number('privateKeyBits', $defaultKeySize, ['required' => 'required', 'min' => $minimumKeySize]) ?>
                <span class="input-group-addon"><i class="fa fa-asterisk"></i></span>
            </div>
        </div>
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= $resolverManager->resolve(['/dashboard/system/acme/accounts']) ?>" class="btn btn-default pull-left"><?= t('Cancel') ?></a>
            <input type="submit" class="btn btn-primary pull-right btn ccm-input-submit" value="<?= t('Add') ?>">
        </div>
    </div>

</form>

<script>
$(document).ready(function() {
    var $useExisting = $('#useExisting'),
        $privateKeyBits = $('#privateKeyBits'),
        $privateKey = $('#privateKey'),
        $form = $useExisting.closest('form'),
        submitted = false;
    $form.on('submit', function(e) {
        if (submitted) {
            e.preventDefault();
        } else {
            submitted = true;
        }
    });
    $useExisting
        .on('change', function() {
            var useExisting = $useExisting.is(':checked');
            $privateKeyBits.closest('fieldset').toggle(!useExisting);
            $privateKey.closest('div.form-group').toggle(useExisting);
            if (useExisting) {
                $privateKeyBits.removeAttr('required');
                $privateKey.attr('required', 'required');
            } else {
                $privateKeyBits.attr('required', 'required');
                $privateKey.removeAttr('required');
            }
        })
        .trigger('change')
    ;
});
</script>