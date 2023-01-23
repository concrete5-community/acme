<?php

use Acme\Crypto\FileDownloader;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\Certificate $certificate
 * @var Acme\Entity\Domain[] $applicableDomains
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Html\Service\Html $html
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Page\View\PageView $view
 * @var int $defaultKeySize
 * @var int $minimumKeySize
 * @var Acme\Service\UI $ui
 */

$canEdit = $applicableDomains !== [] && $certificate->getCsr() === '' && $certificate->getOngoingOrder() === null;

if ($certificate->getID() !== null) {
    ?>
    <form method="POST" action="<?= h($view->action('delete', $certificate->getID())) ?>" id="acme-certificate-delete" class="<?= $ui->displayNone ?>">
        <?php $token->output('acme-certificate-delete-' . $certificate->getID()) ?>
    </form>
    <?php
}
?>
<form method="POST" action="<?= h($view->action('submit', $certificate->getID() ?: 'new', $certificate->getAccount()->getID())) ?>">
    <?php
    $token->output('acme-certificate-edit-' . ($certificate->getID() ?: 'new') . '-' . $certificate->getAccount()->getID());
    ?>

    <fieldset>
        <legend><?= t('Included domains') ?></legend>
        <?php
        if ($applicableDomains === []) {
            ?>
            <div class="alert alert-error">
                <?= t("There's no domain associated to the '%s' account", $certificate->getAccount()->getName()) ?>
            </div>
            <?php
        }
        elseif ($canEdit) {
            ?>
            <table class="table table-striped" style="width: auto" id="acme-certificate-domains">
                <thead>
                    <tr>
                        <th><?= t('Domain') ?></th>
                        <th><?= t('Include') ?></th>
                        <th><?= t('Primary') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $primaryDomainID = null;
                    $selectedDomainsMap = [];
                    if ($certificate->getID() !== null) {
                        foreach ($certificate->getDomains() as $certificateDomain) {
                            $selectedDomainsMap[$certificateDomain->getDomain()->getID()] = $certificateDomain;
                            if ($primaryDomainID === null && $certificateDomain->isPrimary()) {
                                $primaryDomainID = $certificateDomain->getDomain()->getID();
                            }
                        }
                    }
                    foreach ($applicableDomains as $domain) {
                        ?>
                        <tr>
                            <td>
                                <label for="acme-domain-<?= $domain->getID() ?>"><?= h($domain->getHostDisplayName()) ?></label>
                            </td>
                            <td>
                                <?= $form->checkbox('domains[]', $domain->getID(), isset($selectedDomainsMap[$domain->getID()])) ?>
                            </td>
                            <td>
                                <?= $form->radio('primaryDomain', $domain->getID(), $primaryDomainID, ['required' => 'required']) ?>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
            <?php
        } else {
            ?>
            <ul>
                <?php
                foreach ($certificate->getDomains() as $certificateDomain) {
                    ?>
                    <li>
                        <?php
                        if ($certificateDomain->isPrimary()) {
                            echo '<strong>';
                        }
                        echo h($certificateDomain->getDomain()->getHostDisplayName());
                        if ($certificateDomain->isPrimary()) {
                            echo '</strong>';
                        }
                        ?>
                    </li>
                    <?php
                }
                ?>
            </ul>
            <div class="text-muted">
                <?php
                if ($certificate->getCsr() !== '') {
                    ?>
                    <?= t("It's not possible to change the list of domains since the certificate is active") ?>
                    <?php
                } elseif ($certificate->getOngoingOrder() !== null) {
                    ?>
                    <?= t("It's not possible to change the list of domains since there's a currently active authorization process") ?>
                    <?php
                }
                ?>
            </div>
            <?php
        }
        ?>
    </fieldset>

    <fieldset>
        <legend><?= t('Advanced options') ?></legend>
        <?php
        if ($certificate->getID() === null) {
            ?>
            <div class="form-group">
                <?= $form->label('privateKeyBits', t('Size of private key to create')) ?>
                <div class="input-group">
                    <?= $form->number('privateKeyBits', $defaultKeySize, ['required' => 'required', 'min' => $minimumKeySize]) ?>
                    <span class="<?= $ui->inputGroupAddon ?>"><i class="<?= $ui->faAsterisk ?>"></i></span>
                </div>
            </div>
            <?php
        } else {
            $certificateInfo = $certificate->getCertificateInfo();
            $view->element(
                'file_downloader',
                [
                    'downloadUrl' => (string) $view->action('download_key', $certificate->getID()),
                    'downloadTokenName' => $token::DEFAULT_TOKEN_NAME,
                    'downloadTokenValue' => $token->generate('acme-download-certificate-key-' . $certificate->getID()),
                    'what' => 0
                        | ($certificate->getCsr() === '' ? 0 : FileDownloader::WHAT_CSR)
                        | ($certificateInfo === null ? 0 : FileDownloader::WHAT_CERTIFICATE)
                        | ($certificateInfo === null || $certificateInfo->getIssuerCertificate() === '' ? 0 : FileDownloader::WHAT_ISSUERCERTIFICATE)
                        | FileDownloader::WHAT_PUBLICKEY
                        | FileDownloader::WHAT_PRIVATEKEY,
                    'form' => $form,
                    'ui' => $ui,
                ],
                'acme'
            );
        }
        ?>
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/certificates'])) ?>" class="btn <?= $ui->defaultButton ?> <?= $ui->floatStart ?>">
                <?php
                if ($certificate->getID() === null || $applicableDomains === [] || $canEdit) {
                    echo t('Cancel');
                } else {
                    echo t('Back');
                }
                ?>
            </a>
            <div class="<?= $ui->floatEnd ?>">
                <?php
                if ($certificate->getID() !== null) {
                    ?>
                    <a href="#" id="acme-btn-delete" class="btn btn-danger"><?= t('Delete') ?></a>
                    <?php
                }
                if ($certificate->getID() !== null) {
                    if ($certificate->getOrders()->isEmpty()) {
                        ?>
                        <button class="btn btn-primary" disabled="disabled"><?= t('Renewal history') ?></button>
                        <?php
                    } else {
                        ?>
                        <a href="<?= $resolverManager->resolve(['/dashboard/system/acme/certificates/renewals', $certificate->getID()])?>" class="btn btn-primary"><?= t('Renewal history') ?></a>
                        <?php
                    }
                    if ($certificate->getRevokedCertificates()->isEmpty()) {
                        ?>
                        <button class="btn btn-primary" disabled="disabled"><?= t('Revoked certificates') ?></button>
                        <?php
                    } else {
                        ?>
                        <a href="<?= $resolverManager->resolve(['/dashboard/system/acme/certificates/revoked', $certificate->getID()])?>" class="btn btn-primary"><?= t('Revoked certificates') ?></a>
                        <?php
                    }
                }
                if ($applicableDomains === []) {
                    ?>
                    <button class="btn btn-primary" disabled="disabled"><?= t('Save') ?></button>
                    <?php
                } elseif ($canEdit) {
                    ?>
                    <input type="submit" class="btn btn-primary" value="<?= t('Save') ?>" />
                    <?php
                }
                ?>
            </div>
        </div>
    </div>

</form>

<script>$(document).ready(function() {
'use strict';

<?php
if ($certificate->getID() !== null) {
    ?>
    var alreadyDeleted = false;
    $('a#acme-btn-delete').on('click', function(e) {
        e.preventDefault();
        if (window.confirm(<?= json_encode(t('Are you sure you want to delete this certificate?')) ?>)) {
            $('form#acme-certificate-delete').submit();
        }
    });
    <?php
}
?>

function fixCheckboxLabels() {
    $('#acme-certificate-domains input[type="checkbox"]').each(function() {
        var $checkbox = $(this);
        $checkbox.closest('tr').find('label').prop('htmlFor', $checkbox.attr('id'));
    });
}

function updateDomainChecks() {
    var $checkboxes = $('#acme-certificate-domains input[type="checkbox"]');
    $checkboxes.each(function() {
        var $checkbox = $(this),
            $row = $checkbox.closest('tr'),
            $radio = $row.find('input[type="radio"]')
        ;
        if ($checkbox.is(':checked')) {
            $row.toggleClass('domain-selected-1', true).toggleClass('domain-selected-0', false);
            $radio.prop('disabled', false);
        } else {
            $row.toggleClass('domain-selected-0', true).toggleClass('domain-selected-1', false);
            $radio.prop('disabled', true).prop('checked', false);
        }
    });
    var $enabledRadios = $('#acme-certificate-domains input[type="radio"]:enabled');
    if ($enabledRadios.length !== 0 && $enabledRadios.filter(':checked').length === 0) {
        $enabledRadios.filter(':first').prop('checked', true);
    }
}

function selectSingleDomain() {
    var $checkboxes = $('#acme-certificate-domains input[type="checkbox"]');
    if ($checkboxes.length === 1) {
        $checkboxes.prop('checked', true).trigger('change');
    }
}

$('#acme-certificate-domains input').on('change', function() {
    updateDomainChecks();
});

updateDomainChecks();

selectSingleDomain();

});
</script>
