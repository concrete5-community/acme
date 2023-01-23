<?php

use Acme\Filesystem\RemoteDriverInterface;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\RemoteServer $remoteServer
 * @var array $availableDrivers
 * @var Concrete\Core\Localization\Service\Date $dateHelper
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Page\View\PageView $view
 * @var Acme\Service\UI $ui
 */

if ($remoteServer->getID()) {
    ?>
    <form method="POST" action="<?= h($view->action('delete', $remoteServer->getID())) ?>" id="acme-remoteserver-delete" class="<?= $ui->displayNone ?>">
        <?php $token->output('acme-remoteserver-delete-' . $remoteServer->getID()) ?>
    </form>
    <?php
}
?>
<form method="POST" action="<?= h($view->action('submit', $remoteServer->getID() ?: 'new')) ?>">
    <?php $token->output('acme-remoteserver-edit-' . ($remoteServer->getID() ?: 'new')) ?>

    <?= $form->getAutocompletionDisabler() ?>

    <div class="form-group">
        <?= $form->label('name', t('Remote server name')) ?>
        <div class="input-group">
            <?= $form->text('name', $remoteServer->getName(), ['maxlength' => '190', 'required' => 'required', 'placeholder' => t('Mnemonic name of your choice')]) ?>
            <span class="<?= $ui->inputGroupAddon ?>"><i class="<?= $ui->faAsterisk ?>"></i></span>
        </div>
    </div>

    <div class="form-group">
        <?= $form->label('hostname', t('Host name / IP address')) ?>
        <div class="input-group">
            <?= $form->text('hostname', $remoteServer->getHostname(), ['maxlength' => '255', 'required' => 'required']) ?>
            <span class="<?= $ui->inputGroupAddon ?>"><i class="<?= $ui->faAsterisk ?>"></i></span>
        </div>
    </div>

    <div class="form-group">
        <?= $form->label('driver', t('Driver')) ?>
        <div class="input-group">
            <?php
            $driverOptions = [];
            if (!isset($availableDrivers[$remoteServer->getDriverHandle()])) {
                $driverOptions[''] = '';
            }
            foreach ($availableDrivers as $availableDriverHandle => $availableDriverData) {
                $driverOptions[$availableDriverHandle] = $availableDriverData['name'];
            }
            echo $form->select('driver', $driverOptions, $remoteServer->getDriverHandle(), ['required' => 'required']);
            ?>
            <span class="<?= $ui->inputGroupAddon ?>"><i class="<?= $ui->faAsterisk ?>"></i></span>
        </div>
    </div>

    <div class="form-group" style="display: none">
        <?= $form->label('username', t('Username')) ?>
        <?= $form->text('username', $remoteServer->getUsername(), ['maxlength' => '255']) ?>
    </div>

    <div class="form-group" style="display: none">
        <?php
        echo $form->label('password', t('Password'));
        if ($remoteServer->getID()) {
            ob_start();
            ?>
            <input type="hidden" name="change-password" id="change-password" value="1" />
            <?= $form->password('password', '', ['maxlength' => '255']) ?>
            <?php
            $passwordHtml = ob_get_clean();
            if ($view->post('change-password')) {
                echo $passwordHtml;
            } else {
                ?>
                <div><a href="#" class="btn <?= $ui->defaultButton ?>" onclick="<?= h("$(this).closest('div').replaceWith(" . json_encode($passwordHtml) . "); $('#password').focus(); return false;") ?>"><?= t('Change') ?></a></div>
                <?php
            }
        } else {
            echo $form->password('password', '', ['maxlength' => '255']);
        }
        ?>
    </div>

    <div class="form-group" style="display: none">
        <?php
        echo $form->label('privateKey', t('Private key'));
        if ($remoteServer->getID()) {
            ob_start();
            ?>
            <input type="hidden" name="change-privateKey" id="change-privateKey" value="1" />
            <?= $form->textarea('privateKey', ['rows' => '10', 'style' => 'resize: vertical; font-family: monospace']) ?>
            <?php
            $privateKeyHtml = ob_get_clean();
            if ($view->post('change-privateKey')) {
                echo $privateKeyHtml;
            } else {
                ?>
                <div><a href="#" class="btn <?= $ui->defaultButton ?>" onclick="<?= h("$(this).closest('div').replaceWith(" . json_encode($privateKeyHtml) . "); $('#privateKey').focus(); return false;") ?>"><?= t('Change') ?></a></div>
                <?php
            }
        } else {
            echo $form->textarea('privateKey', ['rows' => '10', 'style' => 'resize: vertical; font-family: monospace']);
        }
        ?>
    </div>

    <div class="form-group" style="display: none">
        <?= $form->label('sshAgentSocket', t('Socket name of the SSH agent')) ?>
        <?= $form->text('sshAgentSocket', $remoteServer->getSshAgentSocket(), ['maxlength' => '255']) ?>
        <div class="small text-muted"><?= t("If empty, we'll use the value of the %s environment variable", '<code>SSH_AUTH_SOCK</code>') ?></div>
    </div>

    <div class="row">

        <div class="col-md-6">
            <div class="form-group">
                <?= $form->label('port', t('Connection port')) ?>
                <?= $form->number('port', $remoteServer->getPort(), ['min' => '1', 'max' => 0xffff]) ?>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <?= $form->label('connectionTimeout', t('Connection timeout')) ?>
                <div class="input-group">
                    <?= $form->number('connectionTimeout', $remoteServer->getConnectionTimeout(), ['min' => '1']) ?>
                    <span class="<?= $ui->inputGroupAddon ?>"><?= Punic\Unit::getName('duration/second', 'long') ?></span>
                </div>
            </div>
        </div>

    </div>

    <?php
    if ($remoteServer->getID()) {
        ?>
        <div class="form-group">
            <?= $form->label('', t('Created on')) ?>
            <div class="form-control"><?= h($dateHelper->formatDateTime($remoteServer->getCreatedOn(), true, true)) ?></div>
        </div>

        <div class="form-group">
            <?= $form->label('', t('Used for certificate actions')) ?>
            <div class="form-control">
                <?= t2(
                    'This remote server is used in %s certificate action',
                    'This remote server is used in %s certificate actions',
                    $remoteServer->getCertificateActions()->count()
                ) ?>
            </div>
        </div>
        <?php
    }
    ?>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/remote_servers'])) ?>" class="btn <?= $ui->defaultButton ?> <?= $ui->floatStart ?>"><?= t('Cancel') ?></a>
            <div class="<?= $ui->floatEnd ?>">
                <?php
                if ($remoteServer->getID()) {
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

    var availableDrivers = <?= json_encode($availableDrivers) ?>;

    $('#driver')
        .on('change', function() {
            var driverHandle = this.value,
                driverData = driverHandle && availableDrivers.hasOwnProperty(driverHandle) ? availableDrivers[driverHandle] : null,
                loginFlags = driverData ? driverData.loginFlags : 0;
            $('label[for="username"]').closest('.form-group').toggle((loginFlags & <?= RemoteDriverInterface::LOGINFLAG_USERNAME ?>) !== 0);
            $('label[for="password"]').closest('.form-group').toggle((loginFlags & <?= RemoteDriverInterface::LOGINFLAG_PASSWORD ?>) !== 0);
            $('label[for="privateKey"]').closest('.form-group').toggle((loginFlags & <?= RemoteDriverInterface::LOGINFLAG_PRIVATEKEY ?>) !== 0);
            $('label[for="sshAgentSocket"]').closest('.form-group').toggle((loginFlags & <?= RemoteDriverInterface::LOGINFLAG_SSHAGENT ?>) !== 0);
        })
        .trigger('change')
    ;

    <?php
    if ($remoteServer->getID()) {
        ?>
        var alreadyDeleted = false;
        $('a#acme-btn-delete').on('click', function(e) {
            e.preventDefault();
            if (!alreadyDeleted && window.confirm(<?= json_encode(t('Are you sure you want to delete this remote server?')) ?>)) {
                alreadyDeleted = true;
                $('form#acme-remoteserver-delete').submit();
            }
        });
        <?php
    }
    ?>

});
</script>
