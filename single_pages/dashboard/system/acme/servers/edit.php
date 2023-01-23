<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\Server $server
 * @var bool $otherServersExists
 * @var array $sampleServers
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 * @var Concrete\Core\Localization\Service\Date $dateHelper
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Page\View\PageView $view
 * @var Acme\Service\UI $ui
 */

if ($server->getID()) {
    ?>
    <form method="POST" action="<?= h($view->action('delete', $server->getID())) ?>" id="acme-server-delete" class="<?= $ui->displayNone ?>">
        <?php $token->output('acme-server-delete-' . $server->getID()) ?>
    </form>
    <?php
}
?>
<form method="POST" action="<?= h($view->action('submit', $server->getID() ?: 'new')) ?>">
    <?php $token->output('acme-server-edit-' . ($server->getID() ?: 'new')) ?>

    <div class="form-group">
        <?= $form->label('name', t('Server name')) ?>
        <div class="input-group">
            <?= $form->text('name', $server->getName(), ['maxlength' => '190', 'required' => 'required', 'placeholder' => t('Mnemonic name of your choice')]) ?>
            <span class="<?= $ui->inputGroupAddon ?>"><i class="<?= $ui->faAsterisk ?>"></i></span>
        </div>
    </div>

    <div class="form-group">
        <?= $form->label('name', t('URL of the directory')) ?>
        <div class="input-group">
            <?php
            $directoryUrlAttributes = ['maxlength' => '255', 'required' => 'required'];
            if ($server->getID() !== null && !$server->getAccounts()->isEmpty()) {
                $directoryUrlAttributes += ['readonly' => 'readonly'];
            }
            ?>
            <?= $form->url('directoryUrl', $server->getDirectoryUrl(), $directoryUrlAttributes) ?>
            <span class="<?= $ui->inputGroupAddon ?>"><i class="<?= $ui->faAsterisk ?>"></i></span>
        </div>
        <?php
        if ($server->getID() !== null && !$server->getAccounts()->isEmpty()) {
            ?>
            <div class="small text-muted">
                <?= t("Since there are accounts associated to this server, you shouldn't change this value.") ?>
                <?= t('If you still want to do that, %sclick here%s.', '<a href="#" id="acme-allow-change-directory-url">', '</a>') ?>
            </div>
            <?php
        }
        elseif (!empty($sampleServers)) {
            ?>
            <div class="small text-muted">
                <strong><?= t('Sample servers') ?></strong>
                <table>
                    <tbody>
                    <?php
                    $sampleServersCategoryNames = [
                        'production' => t('Production Servers'),
                        'staging' => t('Staging Servers'),
                        'test' => t('Test Servers'),
                    ];
                    foreach ($sampleServers as $sampleServerCategory => $sampleServerList) {
                        if (!empty($sampleServerList)) {
                            $sampleServerList = array_filter($sampleServerList);
                        }
                        if (empty($sampleServerList)) {
                            continue;
                        }
                        ?>
                        <tr>
                            <td><?= h(isset($sampleServersCategoryNames[$sampleServerCategory]) ? $sampleServersCategoryNames[$sampleServerCategory] : $sampleServerCategory) ?>&nbsp;</td>
                            <td>
                                <?php
                                foreach ($sampleServerList as $sampleServer) {
                                    ?>
                                    <a
                                        class="acme-sample-server <?= $ui->badgePrimary ?>"
                                        href="#"
                                        data-directoryurl="<?= h(array_get($sampleServer, 'directoryUrl')) ?>"
                                        data-authorizationports="<?= h(implode(', ', array_get($sampleServer, 'authorizationPorts', []))) ?>"
                                        data-allowunsafeconnections="<?= array_get($sampleServer, 'allowUnsafeConnections') ? '1' : '0' ?>"
                                    ><?= h(array_get($sampleServer, 'name')) ?></a>
                                    <?php
                                }
                                ?>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>

            </div>
            <?php
        }
        ?>
    </div>

    <div class="form-group">
        <?= $form->label('name', t('Authorization ports')) ?>
        <div class="input-group">
            <?= $form->text('authorizationPorts', implode(' ', $server->getAuthorizationPorts()), ['required' => 'required']) ?>
            <span class="<?= $ui->inputGroupAddon ?>"><i class="<?= $ui->faAsterisk ?>"></i></span>
        </div>
        <div class="small text-muted">
            <div>
                <?= t('Here you can specify the ports that the ACME server will use to contact the domains to be authorized.') ?><br />
                <?= t('You can specify multiple ports by separating them with spaces or commas.') ?>
            </div>
        </div>
    </div>

    <div class="form-group">
        <?= $form->label('', t('Options')) ?>
        <div class="checkbox">
            <label>
                <?= $form->checkbox('allowUnsafeConnections', '1', $server->isAllowUnsafeConnections()) ?>
                <?= t('Allow unsafe connections') ?>
                <span class="small text-muted" style="display:block"><i class="text-danger <?= $ui->faExclamationTriangle ?>"></i> <?= t('this option should be enabled for development/testing purposes only!') ?></span>
            </label>
        </div>
        <?php
        if ($otherServersExists) {
            ?>
            <div class="checkbox">
                <label>
                    <?= $form->checkbox('default', '1', $server->isDefault()) ?>
                    <?= t('Set as default server') ?>
                </label>
            </div>
            <?php
        }
        ?>
    </div>

    <?php
    if ($server->getID()) {
        ?>
        <div class="form-group">
            <?= $form->label('', t('Created on')) ?>
            <div class="form-control"><?= h($dateHelper->formatDateTime($server->getCreatedOn(), true, true)) ?></div>
        </div>

        <div class="form-group">
            <?= $form->label('', t('Used for accounts')) ?>
            <div>
                <?php
                if ($server->getAccounts()->count() === 0) {
                    ?><i><?= tc('NoAccounts', 'none') ?></i><?php
                } else {
                    ?>
                    <ol>
                        <?php
                        foreach ($server->getAccounts() as $account) {
                            ?><li><?= h($account->getName()) ?></li><?php
                        }
                        ?>
                    </ol>
                <?php
                }
            ?>
            </div>
        </div>
        <?php
    }
    ?>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/servers'])) ?>" class="btn <?= $ui->defaultButton ?> <?= $ui->floatStart ?>"><?= t('Cancel') ?></a>
            <div class="<?= $ui->floatEnd ?>">
                <?php
                if ($server->getID()) {
                    ?>
                    <a href="#" id="acme-btn-delete" class="btn btn-danger"><?= t('Delete') ?></a>
                    <?php
                }
                ?>
                <input type="submit" class="btn btn-primary ccm-input-submit" value="<?= t('Save') ?>">
            </div>
        </div>
    </div>

</form>

<script>
$(document).ready(function() {
    $('#acme-allow-change-directory-url').on('click', function(e) {
        e.preventDefault();
        $('#directoryUrl').removeAttr('readonly');
        $('#acme-allow-change-directory-url').closest('div').remove();
    });
    $('a.acme-sample-server').on('click', function(e) {
        e.preventDefault();
        var $a = $(this);
        $('#directoryUrl').val($a.data('directoryurl') || '');
        $('#authorizationPorts').val($a.data('authorizationports') || '');
        $('#allowUnsafeConnections').prop('checked', parseInt($a.data('allowunsafeconnections')) ? true : false);
    });
    <?php
    if ($server->getID()) {
        ?>
        var alreadyDeleted = false;
        $('a#acme-btn-delete').on('click', function(e) {
            e.preventDefault();
            if (!alreadyDeleted && window.confirm(<?= json_encode(t('Are you sure you want to delete this ACME server?')) ?>)) {
                alreadyDeleted = true;
                $('form#acme-server-delete').submit();
            }
        });
        <?php
    }
    ?>
});
</script>
