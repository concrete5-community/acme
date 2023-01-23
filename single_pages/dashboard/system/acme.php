<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var array $pages
 */
?>
<p><?= t('In this section of the site you can manage the HTTPS certificates of your websites.') ?></p>
<table class="table">
    <colgroup>
        <col width="1" />
    </colgroup>
    <tbody>
        <?php
        foreach ($pages as $page) {
            ?>
            <tr>
                <td><a href="<?= h($page['url']) ?>" class="btn btn-primary" style="width:100%"><?= h($page['name']) ?></a></td>
                <td>
                    <?php
                    switch ($page['kind']) {
                        case 'servers':
                            ?>
                            <div><?= t('Here you can manage the ACME servers.') ?></div>
                            <div><?= t("An ACME server is a server provided by someone (for example, Let's Encrypt) that will create the HTTPS certificates for your websites.") ?></div>
                            <div class="small text-muted"><div><?= t('Number of currently defined ACME servers: %s', $page['servers']) ?></div></div>
                            <?php
                            break;
                        case 'accounts':
                            ?>
                            <div><?= t('Here you can manage the accounts in the ACME servers.') ?></div>
                            <div><?= t('In order to create HTTPS certificates, you need to create an account in an ACME server.') ?></div>
                            <div class="small text-muted"><div><?= t('Number of currently defined accounts: %s', $page['accounts']) ?></div></div>
                            <?php
                            break;
                        case 'domains':
                            ?>
                            <div><?= t('Here you can manage the domains for which you want the HTTPS certificates.') ?></div>
                            <div><?= t('In order to create an HTTPS certificate for a website, the ACME server have to be sure that you own the domain.') ?></div>
                            <div class="small text-muted"><div><?= t('Number of currently defined domains: %s', $page['domains']) ?></div></div>
                            <?php
                            break;
                        case 'certificates':
                            ?>
                            <div><?= t('Here you can manage the HTTPS certificates.') ?></div>
                            <div class="small text-muted"><div><?= t('Number of currently defined certificates: %s', $page['certificates']) ?></div></div>
                            <?php
                            break;
                        case 'remote_servers':
                            ?>
                            <div><?= t('Here you can manage your remote servers.') ?></div>
                            <div><?= t('When an ACME Server generates an HTTPS certificate, you may want to upload it to a remote server.') ?></div>
                            <div class="small text-muted"><div><?= t('Number of currently defined remote servers: %s', $page['remote_servers']) ?></div></div>
                            <?php
                            break;
                        case 'options':
                            ?>
                            <div><?= t('Here you can configure the general options of the ACME package.') ?></div>
                            <?php
                            break;
                        case 'revoked_certificates':
                            ?>
                            <div><?= t('Here you view the certificates issued for deleted certificates.') ?></div>
                            <div><?= t('When you delete a certificate, its data is stored here for logging purposes.') ?></div>
                            <div class="small text-muted"><div><?= t('Number of revoked certificates: %s', $page['revoked_certificates']) ?></div></div>
                            <?php
                            break;
                    }
                    ?>
                </td>
            </tr>
            <?php
        }
        ?>
    </tbody>
</table>
