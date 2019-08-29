<?php

use Acme\Security\FileDownloader;
use phpseclib\Crypt\RSA;
use phpseclib\File\X509;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * Arguments:.
 *
 * @var string $downloadUrl
 * @var string $downloadTokenName
 * @var string $downloadTokenValue
 * @var int $what @see Acme\Security\FileDownloader
 * @var Concrete\Core\Form\Service\Form $form
 */
$what = (int) $what;
if ($what === 0) {
    return;
}
$id = 'acme-filedownloader-' . preg_replace('/[^\w\_]/', '_', uniqid('id', true) . '_' . mt_rand());
?>
<div id="<?= h($id) ?>">
    <?php
    if ($what & FileDownloader::WHAT_CSR) {
        ?>
        <div class="form-group">
            <?= $form->label('', t('Download CSR')) ?>
            <div>
                <div class="btn-group">
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_CSR ?>" data-key-format="<?= h(X509::FORMAT_PEM) ?>"><?= t('ASCII Format (PEM)') ?></a>
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_CSR ?>" data-key-format="<?= h(X509::FORMAT_DER) ?>"><?= t('Binary Format (DER)') ?></a>
                </div>
            </div>
        </div>
        <?php
    }
    if ($what & FileDownloader::WHAT_CERTIFICATE) {
        ?>
        <div class="form-group">
            <?= $form->label('', t('Download certificate')) ?>
            <div>
                <div class="btn-group">
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_CERTIFICATE ?>" data-key-format="<?= h(X509::FORMAT_PEM) ?>"><?= t('ASCII Format (PEM)') ?></a>
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_CERTIFICATE ?>" data-key-format="<?= h(X509::FORMAT_DER) ?>"><?= t('Binary Format (DER)') ?></a>
                </div>
            </div>
        </div>
        <?php
    }
    if ($what & FileDownloader::WHAT_ISSUERCERTIFICATE) {
        ?>
        <div class="form-group">
            <?= $form->label('', t('Download issuer certificate')) ?>
            <div>
                <div class="btn-group">
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_ISSUERCERTIFICATE ?>" data-key-format="<?= h(X509::FORMAT_PEM) ?>"><?= t('ASCII Format (PEM)') ?></a>
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_ISSUERCERTIFICATE ?>" data-key-format="<?= h(X509::FORMAT_DER) ?>"><?= t('Binary Format (DER)') ?></a>
                </div>
            </div>
        </div>
        <?php
    }
    if ($what & FileDownloader::WHAT_PUBLICKEY) {
        ?>
        <div class="form-group">
            <?= $form->label('', t('Download public key')) ?>
            <div>
                <div class="btn-group">
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_PUBLICKEY ?>" data-key-format="<?= h(RSA::PUBLIC_FORMAT_PKCS1) ?>"><?= t('PKCS#1 (PEM)') ?></a>
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_PUBLICKEY ?>" data-key-format="<?= h(RSA::PUBLIC_FORMAT_PKCS8) ?>"><?= t('PKCS#8 (PEM)') ?></a>
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_PUBLICKEY ?>" data-key-format="<?= h(RSA::PUBLIC_FORMAT_XML) ?>"><?= t('XML') ?></a>
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_PUBLICKEY ?>" data-key-format="<?= h(RSA::PUBLIC_FORMAT_OPENSSH) ?>"><?= t('OpenSSH') ?></a>
                </div>
            </div>
        </div>
        <?php
    }
    if ($what & FileDownloader::WHAT_PRIVATEKEY) {
        ?>
        <div class="form-group">
            <?= $form->label('', t('Download private key')) ?>
            <div class="alert alert-danger" style="margin-bottom: 0">
                <?= t('Warning! Transferring private keys can be unsecure. Use wisely.') ?><br />
                <div class="btn-group">
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_PRIVATEKEY ?>" data-key-format="<?= h(RSA::PRIVATE_FORMAT_PKCS1) ?>"><?= t('PKCS#1 (PEM)') ?></a>
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_PRIVATEKEY ?>" data-key-format="<?= h(RSA::PRIVATE_FORMAT_PKCS8) ?>"><?= t('PKCS#8 (PEM)') ?></a>
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_PRIVATEKEY ?>" data-key-format="<?= h(RSA::PRIVATE_FORMAT_XML) ?>"><?= t('XML') ?></a>
                    <a href="#" class="btn btn-default acme-key-download" data-key-what="<?= FileDownloader::WHAT_PRIVATEKEY ?>" data-key-format="<?= h(RSA::PRIVATE_FORMAT_PUTTY) ?>"><?= t('PuTTY') ?></a>
                </div>
            </div>
        </div>
        <?php
    }
    ?>
</div>
<script>$(document).ready(function() {
'use strict';

$(<?= json_encode("#{$id} a.acme-key-download") ?>).on('click', function(e) {
    e.preventDefault();
    var $a = $(this),
        $form = $('<form method="POST" class="hide" />')
            .attr('action', <?= json_encode($downloadUrl) ?>)
            .append($('<input type="hidden" />').attr('name', <?= json_encode($downloadTokenName) ?>).val(<?= json_encode($downloadTokenValue) ?>))
            .append($('<input type="hidden" name="what" />').val($a.data('key-what')))
            .append($('<input type="hidden" name="format" />').val($a.data('key-format')))
    ;
    $(document.body).append($form);
    $form.submit().remove();
});

});
</script>
