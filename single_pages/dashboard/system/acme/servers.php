<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\Server[] $servers
 * @var Acme\Protocol\Version $protocolVersion
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 * @var Concrete\Core\Localization\Service\Date $dateHelper
 */
?>
<div class="ccm-dashboard-header-buttons">
    <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/servers/edit', 'new'])) ?>" class="btn btn-primary"><?= t('Add ACME server') ?></a>
</div>

<?php
if (empty($servers)) {
    ?>
    <div class="alert alert-info">
        <?= t('No server has been defined.') ?>
    </div>
    <?php
} else {
    ?>
    <table class="table table-striped table-condensed">
        <col width="1">
        <thead>
            <tr>
                <th></th>
                <th><?= t('Default') ?></th>
                <th><?= t('Name') ?></th>
                <th><?= t('Created on') ?></th>
                <th><?= t('Protocol') ?></th>
                <th><?= t('Accounts') ?></th>
                <th><?= t('Entry point') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($servers as $server) {
                ?>
                <tr>
                    <td><a class="btn btn-xs btn-primary" href="<?= h($resolverManager->resolve(['/dashboard/system/acme/servers/edit', $server->getID()])) ?>"><?= t('Edit') ?></a></td>
                    <td class="text-center"><?= $server->isDefault() ? '<i class="fa fa-check-square-o"></i>' : '<i class="fa fa-square-o"></i>' ?></td>
                    <td>
                        <?php
                        if ($server->getWebsite() !== '') {
                            ?><a href="<?= h($server->getWebsite()) ?>" rel="noopener noreferrer" target="_blank"><?php
                        }
                        echo h($server->getName());
                        if ($server->getWebsite() !== '') {
                            ?></a><?php
                        }
                        ?>
                    </td>
                    <td><?= h($dateHelper->formatDateTime($server->getCreatedOn(), true, true)) ?>
                    <td><?= h($protocolVersion->getProtocolVersionName($server->getProtocolVersion())) ?></td>
                    <td class="text-center"><?= $server->getAccounts()->count() ?></td>
                    <td>
                        <?php
                        if ($server->isAllowUnsafeConnections()) {
                            ?><div><span class="label label-danger"><?= t('Unsafe connections allowed')?></span></div><?php
                        }
                        ?>
                        <a href="<?= h($server->getDirectoryUrl()) ?>" rel="noopener noreferrer" target="_blank"><?= h($server->getDirectoryUrl()) ?></a>
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
    <?php
}
