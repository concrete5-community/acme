<?php

use Acme\Install\Checks;
use Acme\Service\UI;
use Concrete\Core\Package\PackageService;
use Concrete\Core\Support\Facade\Application;
use Concrete\Package\Acme\Controller;

defined('C5_EXECUTE') or die('Access Denied.');

$app = Application::getFacadeApplication();

$packageService = $app->make(PackageService::class);
$packageController = $packageService->getClass('acme');
if (!$packageController instanceof Controller) {
    return;
}
$packageController->setupAutoloader();
$app->make('config')->package($packageController);

$checks = $app->make(Checks::class);
$ui = $app->make(UI::class);

$greenIcon = "<i class=\"{$ui->faCheck}\" style=\"color: green\"></i>";
$orangeIcon = "<i class=\"{$ui->faExclamationTriangle}\" style=\"color: orange\"></i>";
$redIcon = "<i class=\"{$ui->faExclamationCircle}\" style=\"color: red\"></i>";

?>
<div style="display: none">
    <div class="ccm-dashboard-header-buttons">
        <a href="#" class="btn btn-primary" onclick="window.location.reload(); return false"><?= t('Repeat checks') ?></a>
    </div>
</div>
<table class="table table-striped">
    <col />
    <col width="100%" />
    <tbody>
        <tr>
            <td><?= $checks->isOpenSslInstalled() ? $greenIcon : $orangeIcon ?></td>
            <td><?= t('PHP extension %s installed', '<code>openssl</code>') ?></td>
            <td>
                <?php
                if (!$checks->isOpenSslInstalled()) {
                    ?><i class="<?= $ui->faQuestionCircle ?> launch-tooltip" data-html="true" title="<?= h(t('For faster execution, you should enable the %s PHP extension', '<code>openssl</code>')) ?>"></i><?php
                }
                ?>
            </td>
        </tr>
        <?php
        if ($checks->isOpenSslInstalled()) {
            ?>
            <tr>
                <td><?= $checks->isOpenSslMisconfigured() ? $orangeIcon : $greenIcon ?></td>
                <td><?= t('PHP extension %s configured correctly', '<code>openssl</code>') ?></td>
                <td>
                    <?php
                    if ($checks->isOpenSslMisconfigured()) {
                        ?><i class="<?= $ui->faQuestionCircle ?> launch-tooltip" title="<?= h($checks->getOpenSslMisconfigurationProblems()) ?>"></i><?php
                    }
                    ?>
                </td>
            </tr>
            <?php
        }
        ?>
        <tr>
            <td><?= $checks->isFastBigIntegerAvailable() ? $greenIcon : $redIcon ?></td>
            <td><?= t('PHP extension %s or %s installed', '<code>gmp</code>', '<code>bcmath</code>') ?></td>
            <td>
                <?php
                if (!$checks->isFastBigIntegerAvailable()) {
                    ?><i class="<?= $ui->faQuestionCircle ?> launch-tooltip" data-html="true" title="<?= h(t('For faster execution, you should enable the %s and/or the %s PHP extension', '<code>gmp</code>', '<code>bcmath</code>')) ?>"></i><?php
                }
                ?>
            </td>
        </tr>
        <tr>
            <td><?= $checks->isHttpClientWorking() ? $greenIcon : $redIcon ?></td>
            <td><?= t('PHP extension %s installed', '<code>curl</code>') ?></td>
            <td>
                <?php
                if (!$checks->isHttpClientWorking()) {
                    ?><i class="<?= $ui->faQuestionCircle ?> launch-tooltip" title="<?= h($checks->getHttpClientError()) ?>"></i><?php
                }
                ?>
            </td>
        </tr>
        <tr>
            <td><?= $checks->getFtpExtensionState() !== $checks::FTPEXTENSION_UNAVAILABLE ? $greenIcon : $orangeIcon ?></td>
            <td><?= t('PHP extension %s installed', '<code>ftp</code>') ?></td>
            <td>
                <?php
                if ($checks->getFtpExtensionState() === $checks::FTPEXTENSION_UNAVAILABLE) {
                    ?><i class="<?= $ui->faQuestionCircle ?> launch-tooltip" data-html="true" title="<?= h(t('To enable uploading certificates to remote servers with FTP or SSH, you should enable the %s PHP extension', '<code>ftp</code>')) ?>"></i><?php
                }
                ?>
            </td>
        </tr>
        <?php
        if ($checks->getFtpExtensionState() !== $checks::FTPEXTENSION_UNAVAILABLE) {
            ?>
            <tr>
                <td><?= $checks->getFtpExtensionState() === $checks::FTPEXTENSION_OK ? $greenIcon : $orangeIcon ?></td>
                <td><?= t('PHP extension %s installed with SSH support', '<code>ftp</code>') ?></td>
                <td>
                    <?php
                    if ($checks->getFtpExtensionState() !== $checks::FTPEXTENSION_OK) {
                        ?><i class="<?= $ui->faQuestionCircle ?> launch-tooltip" data-html="true" title="<?= h(t('To enable uploading certificates to remote servers with FTP or SSH, you should enable the %s PHP extension', '<code>ftp</code>')) ?>"></i><?php
                    }
                    ?>
                </td>
            </tr>
            <?php
        }
        ?>
    </tbody>
</table>
<?php
if ($checks->isSomeRequirementMissing()) {
    ?><label><input type="checkbox" required="required"> <?= t('I understand that without fixing the errors marked with %s this package could not work properly.', $redIcon) ?></label><?php
}
