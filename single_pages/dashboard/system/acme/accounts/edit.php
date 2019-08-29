<?php

use Acme\Security\FileDownloader;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var ACME\Entity\Account $account
 * @var bool $otherAccountsExist
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Page\View\PageView $view
 * @var Acme\DomainService $domainService
 * @var Concrete\Core\Localization\Service\Date $dateHelper
 */

$domains = $account->getDomains();
?>

<form method="POST" action="<?= $view->action('delete', $account->getID()) ?>" id="acme-account-delete" class="hide">
    <?php $token->output('acme-account-delete-' . $account->getID()) ?>
</form>

<form method="POST" action="<?= $view->action('submit', $account->getID()) ?>">
    <?php $token->output('acme-account-edit-' . $account->getID()) ?>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <?= $form->label('', t('Created on')) ?>
                <div class="form-control"><?= h($dateHelper->formatDateTime($account->getCreatedOn(), true, true)) ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <?= $form->label('', t('ACME Server')) ?>
                <div class="form-control"><?= h($account->getServer()->getName()) ?></div>
            </div>
        </div>
    </div>

    <div class="form-group">
        <?= $form->label('name', t('Name')) ?>
        <div class="input-group">
            <?= $form->text('name', $account->getName(), ['required' => 'required', 'maxlength' => '190', 'placeholder' => t('Give this account a name of your choice')]) ?>
            <span class="input-group-addon"><i class="fa fa-asterisk"></i></span>
        </div>
    </div>

    <div class="form-group">
        <?= $form->label('email', t('Email address')) ?>
        <div class="form-control"><?= h($account->getEmail()) ?></div>
    </div>

    <?php
    if ($otherAccountsExist) {
        ?>
        <div class="form-group">
            <?= $form->label('', t('Options')) ?>
            <div class="checkbox">
                <label>
                    <?= $form->checkbox('default', '1', $account->isDefault()) ?>
                    <?= t('Set as default account') ?>
                </label>
            </div>
        </div>
        <?php
    }
    ?>

    <div class="form-group">
        <?= $form->label('', t('Associated domains')) ?>
        <?php
        if ($domains->count() === 0) {
            ?><div class="alert alert-info"><?= t("There's no domain associated to this account") ?></div><?php
        } else {
            ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?= t('Created on') ?></th>
                        <th><?= t('Domain') ?></th>
                        <th><?= t('State') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($domains as $domain) {
                        ?>
                        <tr>
                            <td><?= h($dateHelper->formatDateTime($domain->getCreatedOn(), true, true)) ?></td>
                            <td><?= h($domain->getHostDisplayName()) ?></td>
                            <td><?= h($domainService->describeChallengeType($domain)) ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
            <?php
        }
        ?>
    </div>

    <fieldset>
        <legend><?= t('Advanced options') ?></legend>

        <?php
        $view->element(
            'file_downloader',
            [
                'downloadUrl' => (string) $view->action('download_key', $account->getID()),
                'downloadTokenName' => $token::DEFAULT_TOKEN_NAME,
                'downloadTokenValue' => $token->generate('acme-account-download_key-' . $account->getID()),
                'what' => FileDownloader::WHAT_PRIVATEKEY | FileDownloader::WHAT_PUBLICKEY,
                'form' => $form,
            ],
            'acme'
        );
        ?>

    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= URL::to('/dashboard/system/acme/accounts') ?>" class="btn btn-default pull-left"><?= t('Cancel') ?></a>
            <div class="pull-right">
                <a href="#" id="acme-btn-delete" class="btn btn-danger"><?= t('Delete') ?></a>
                <input type="submit" class="btn btn-primary ccm-input-submit" value="<?= t('Save') ?>">
            </div>
        </div>
    </div>

</form>
<script>
$(document).ready(function() {
    $('a.acme-key-download').on('click', function(e) {
        e.preventDefault();
        var $a = $(this);

        $('form#acme-key-download')
            .find('input[name="which"]').val($a.data('key-which')).end()
            .find('input[name="format"]').val($a.data('key-format')).end()
            .submit();
    });
    $('a#acme-btn-delete').on('click', function(e) {
        e.preventDefault();
        if (window.confirm(<?= json_encode(
            t('Are you sure you want to delete this account?')
            . (
                $domains->count() === 0 ?
                '' :
                ("\n" . t('WARNING! All the associated domains will be removed!'))
            )
        ) ?>)) {
            $('form#acme-account-delete').submit();
        }
    });
});
</script>