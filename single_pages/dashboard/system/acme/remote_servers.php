<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\RemoteServer[] $remoteServers
 * @var Acme\Filesystem\DriverManager $filesystemDriverManager
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 * @var Concrete\Core\Localization\Service\Date $dateHelper
 */
?>
<div class="ccm-dashboard-header-buttons">
    <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/remote_servers/edit', 'new'])) ?>" class="btn btn-primary"><?= t('Add remote server') ?></a>
</div>

<?php
if ($remoteServers === []) {
    ?>
    <div class="alert alert-info">
        <?= t('No remote server has been defined.') ?>
    </div>
    <?php
    return;
}
?>

<table class="table table-striped table-condensed">
    <col width="1" />
    <thead>
        <tr>
            <th></th>
            <th><?= t('Remote server') ?></th>
            <th><?= t('Host name') ?></th>
            <th><?= t('Driver') ?></th>
            <th><?= t('Username') ?></th>
            <th><?= t('Created on') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($remoteServers as $remoteServer) {
            ?>
            <tr>
                <td><a class="btn btn-sm btn-primary" href="<?= h($resolverManager->resolve(['/dashboard/system/acme/remote_servers/edit', $remoteServer->getID()])) ?>"><?= t('Edit') ?></a></td>
                <td><?= h($remoteServer->getName()) ?></td>
                <td><?= h($remoteServer->getHostname()) ?></td>
                <td><?= h($filesystemDriverManager->getDriverName($remoteServer->getDriverHandle())) ?></td>
                <td><?= h($remoteServer->getUsername()) ?></td>
                <td><?= h($dateHelper->formatDateTime($remoteServer->getCreatedOn(), true, true)) ?>
            </tr>
            <?php
        }
        ?>
    </tbody>
</table>
