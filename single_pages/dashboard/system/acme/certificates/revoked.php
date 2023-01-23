<?php

use Acme\Crypto\FileDownloader;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\Certificate|null $certificate
 * @var Acme\Entity\RevokedCertificate[] $revokedCertificates
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 * @var Concrete\Core\Localization\Service\Date $dateHelper
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Page\View\PageView $view
 * @var Acme\Service\UI $ui
 */

if ($revokedCertificates === []) {
    ?>
    <div class="alert alert-info">
        <?= $certificate === null ? t('There are no revoked certificates for deleted certificates') : t('There are no revoked certificates for this certificate') ?>
    </div>
    <?php
} else {
    ?>
    <table class="table table-striped table-hover">
        <colgroup>
            <col width="1" />
        </colgroup>
        <thead>
            <tr>
                <th></th>
                <th><?= t('Date') ?></th>
                <th><?= t('Domains') ?></th>
                <th><?= t('Revocation problem') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($revokedCertificates as $revokedCertificate) {
                ?>
                <tr>
                    <td>
                        <button class="btn btn-sm btn-info acme-revokedcertificate-details-show" data-revokedcertificate-id="<?= $revokedCertificate->getID() ?>">
                            <?= t('Details')?>
                        </button>
                        <div id="acme-revokedcertificate-details-dialog-<?= $revokedCertificate->getID() ?>" class="<?= $ui->displayNone ?> ccm-ui">
                            <?php
                            $view->element(
                                'file_downloader',
                                [
                                    'downloadUrl' => (string) $view->action('download_key', $certificate === null ? 'unlinked' : $certificate->getID(), $revokedCertificate->getID()),
                                    'downloadTokenName' => $token::DEFAULT_TOKEN_NAME,
                                    'downloadTokenValue' => $token->generate('acme-download-revokedcertificate-key-' . $revokedCertificate->getID()),
                                    'what' => FileDownloader::WHAT_CERTIFICATE | FileDownloader::WHAT_ISSUERCERTIFICATE,
                                    'form' => $form,
                                    'ui' => $ui,
                                ],
                                'acme'
                            );
                            ?>
                            <div class="dialog-buttons">
                                <button class="btn btn-primary <?= $ui->floatEnd ?>" onclick="$(this).closest('.ui-dialog').find('.ui-dialog-content').dialog('close')"><?= t('Close') ?></button>
                            </div>
                        </div>
                    </td>
                    <td><?= h($dateHelper->formatPrettyDateTime($revokedCertificate->getCreatedOn())) ?></td>
                    <td>
                        <?php
                        foreach($revokedCertificate->getCertifiedDomains() as $domain) {
                            echo h($domain), '<br />';
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if ($revokedCertificate->getRevocationFailureMessage() === '') {
                            echo '<i>', t('none'), '</i>';
                        } else {
                            ?>
                            <div class="alert alert-warning" style="margin:0">
                                <?= nl2br(h($revokedCertificate->getRevocationFailureMessage())) ?>
                            </div>
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
    <?php
}
?>
<div class="ccm-dashboard-form-actions-wrapper">
    <div class="ccm-dashboard-form-actions">
        <?php
        if ($certificate === null) {
            ?>
            <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme'])) ?>" class="btn <?= $ui->defaultButton ?> <?= $ui->floatStart ?>">
                <?= t('Back') ?>
            </a>
            <?php
        } else {
            ?>
            <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/certificates/edit', $certificate->getID()])) ?>" class="btn <?= $ui->defaultButton ?> <?= $ui->floatStart ?>">
                <?= t('Back') ?>
            </a>
            <?php
        }
        ?>
        <div class="<?= $ui->floatEnd ?>">
            <?php
            if ($revokedCertificates === []) {
                ?>
                <button disabled="disabled" class="btn btn-danger"><?= t('Delete all') ?></button>
                <?php
            } else {
                ?>
                <button id="acme-certificate-deleterevoked" class="btn btn-danger"><?= t('Delete all') ?></button>
                <?php
            }
            ?>
        </div>
    </div>
</div>

<?php
if ($revokedCertificates !== []) {
    ?>
    <form id="acme-certificate-deleterevoked-do" method="POST" action="<?= h($view->action('delete', $certificate === null ? 'unlinked' : $certificate->getID())) ?>" class="<?= $ui->displayNone ?>">
        <?php $token->output('acme-certificate-clear_history-' . ($certificate === null ? 'unlinked' : $certificate->getID())) ?>
    </form>
    <script>
    $(document).ready(function() {
        $('#acme-certificate-deleterevoked').on('click', function(e) {
            e.preventDefault();
            if (window.confirm(<?= json_encode(t('Are you sure you want to delete the revoked certificates?')) ?>)) {
                $('#acme-certificate-deleterevoked-do').submit();
            }
        });
        $('.acme-revokedcertificate-details-show[data-revokedcertificate-id]').each(function() {
            var $btn = $(this);
            $btn.on('click', function(e) {
                e.preventDefault();
                var $dlg = $('#acme-revokedcertificate-details-dialog-' + $btn.data('revokedcertificate-id'));
                $dlg.dialog({
                    modal: true,
                    width: 500
                });
            });
        });
    });
    </script>
    <?php
}
