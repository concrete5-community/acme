<?php

use Concrete\Core\Url\UrlImmutable;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\Server[] $servers
 * @var Acme\Entity\Account[] $accounts
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 * @var Concrete\Core\Localization\Service\Date $dateHelper
 * @var Acme\Service\UI $ui
 */

$numServers = count($servers);

if ($numServers === 0) {
    ?>
    <div class="alert alert-danger">
        <?= t('No ACME server has been defined: you need to add at least one server <a href="%s">here</a>.', h($resolverManager->resolve(['/dashboard/system/acme/servers']))) ?>
    </div>
    <?php
    return;
}
?>
<div class="ccm-dashboard-header-buttons">
    <?php
    if ($numServers === 1) {
        ?>
        <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/accounts/add', $servers[0]->getID()])) ?>" class="btn btn-primary"><?= t('Add account') ?></a>
        <?php
    } else {
        ?>
        <div class="btn-group">
            <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" data-toggle="dropdown">
                <?= t('Add account') ?>
                <span class="caret"></span>
            </button>
            <ul class="dropdown-menu">
                <?php
                foreach ($servers as $server) {
                    ?>
                    <li>
                        <a class="dropdown-item" href="<?= h($resolverManager->resolve(['/dashboard/system/acme/accounts/add', $server->getID()])) ?>">
                            <?php
                            if ($server->isDefault()) {
                                echo '<strong>', h($server->getName()), '</strong>';
                            } else {
                                echo h($server->getName());
                            }
                            ?>
                        </a>
                    </li>
                    <?php
                }
                ?>
            </ul>
        </div>
        <?php
    }
    ?>
</div>

<?php
if ($accounts === []) {
    ?>
    <div class="alert alert-info">
        <?= t('No account has been defined.') ?>
    </div>
    <?php
} else {
    ?>
    <table class="table table-striped table-condensed">
        <col width="1">
        <col width="1">
        <thead>
            <tr>
                <th></th>
                <th><?= t('Default') ?></th>
                <th><?= t('Name') ?></th>
                <?php
                if ($numServers > 1) {
                    ?><th><?= t('Server') ?></th><?php
                }
                ?>
                <th><?= t('Registered on') ?></th>
                <th><?= t('Email') ?></th>
                <th><?= t('Domains') ?></th>
                <th><?= t('Details') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($accounts as $account) {
                $keyPair = $account->getKeyPair();
                ?>
                <tr>
                    <td><a class="btn btn-sm btn-primary" href="<?= h($resolverManager->resolve(['/dashboard/system/acme/accounts/edit', $account->getID()])) ?>"><?= t('Edit') ?></a></td>
                    <td class="text-center"><?= $account->isDefault() ? "<i class=\"{$ui->faCheckboxChecked}\"></i>" : "<i class=\"{$ui->faCheckboxUnchecked}\"></i>" ?></td>
                    <td><?= h($account->getName()) ?></td>
                    <?php
                    if ($numServers > 1) {
                        ?><td><?= h($account->getServer()->getName()) ?></td><?php
                    }
                    ?>
                    <td><?= h($dateHelper->formatDateTime($account->getRegisteredOn(), true, true)) ?>
                    <td><?= h($account->getEmail()) ?></td>
                    <td class="text-center"><?= $account->getDomains()->count() ?></td>
                    <td>
                        <?php
                        if ($account->getRegistrationURI()) {
                            ?>
                            <?= t('Registered on: %s', h(UrlImmutable::createFromUrl($account->getRegistrationURI())->getAuthority())) ?><br />
                            <?php
                        }
                        ?>
                        <?= t('Key size: %s', $keyPair === null ? '' : $keyPair->getPrivateKeySize()) ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php
}
