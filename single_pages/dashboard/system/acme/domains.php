<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\Server[] $servers
 * @var Acme\Entity\Server[] $serversWithAccounts
 * @var int $numAccounts
 * @var Acme\Entity\Domain[] $domains
 * @var Acme\DomainService $domainService
 * @var Concrete\Core\Localization\Service\Date $dateHelper
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 */
$numServers = count($servers);

if ($numServers === 0) {
    ?>
    <div class="alert alert-danger">
        <?= t('No ACME Server has been defined: you need to add at least one server <a href="%s">here</a>.', h($resolverManager->resolve(['/dashboard/system/acme/servers']))) ?>
    </div>
    <?php
    return;
}

if ($numAccounts === 0) {
    ?>
    <div class="alert alert-danger">
        <?= t('No account has been defined: you need to add at least one account <a href="%s">here</a>.', h($resolverManager->resolve(['/dashboard/system/acme/accounts']))) ?>
    </div>
    <?php
    return;
}

?>
<div class="ccm-dashboard-header-buttons">
    <?php
    if ($numAccounts === 1) {
        $account = $serversWithAccounts[0]->getAccounts()->first();
        ?>
        <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/domains/edit', 'new', $account->getID()])) ?>" class="btn btn-primary"><?= t('Add domain') ?></a>
        <?php
    } else {
        ?>
        <div class="btn-group">
            <button class="btn btn-primary dropdown-toggle" data-toggle="dropdown">
                <?= t('Add domain') ?>
                <span class="caret"></span>
            </button>
            <ul class="dropdown-menu">
                <?php
                if (count($serversWithAccounts) === 1) {
                    foreach ($serversWithAccounts[0]->getAccounts() as $account) {
                        ?>
                        <li>
                            <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/domains/edit', 'new', $account->getID()])) ?>">
                                <?php
                                if ($account->isDefault()) {
                                    echo '<strong>', h($account->getName()), '</strong>';
                                } else {
                                    echo h($account->getName());
                                }
                                ?>
                            </a>
                        </li>
                        <?php
                    }
                } else {
                    foreach ($serversWithAccounts as $server) {
                        ?>
                        <li class="dropdown-header"><?= h($server->getName())?></li>
                        <?php
                        foreach ($server->getAccounts() as $account) {
                            ?>
                            <li>
                                <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/domains/edit', 'new', $account->getID()])) ?>">
                                    <?php
                                    if ($account->isDefault()) {
                                        echo '<strong>', h($account->getName()), '</strong>';
                                    } else {
                                        echo h($account->getName());
                                    }
                                    ?>
                                </a>
                            </li>
                            <?php
                        }
                    }
                }
                ?>
            </ul>
        </div>
        <?php
    }
    ?>
</div>
<?php
if ($domains === []) {
    ?>
    <div class="alert alert-info">
        <?= t('No domain has been defined.') ?>
    </div>
    <?php
    return;
}
$showPunycode = false;
foreach ($domains as $domain) {
    if ($domain->getHostname() !== $domain->getPunycode()) {
        $showPunycode = true;
        break;
    }
}
?>
<table class="table table-striped table-condensed">
    <col width="1" />
    <thead>
        <tr>
            <th></th>
            <th><?= t('Domain') ?></th>
            <?php
            if ($showPunycode) {
                ?>
                <th><?= t('Punycode') ?></th>
                <?php
            }
            ?>
            <th><?= t('Used in certificates') ?></th>
            <?php
            if ($numServers > 1) {
                ?><th><?= t('Server') ?></th><?php
            }
            if ($numAccounts > 1) {
                ?><th><?= t('Account') ?></th><?php
            }
            ?>
            <th><?= t('Created on') ?></th>
            <th><?= t('Authorization method') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($domains as $domain) {
            ?>
            <tr>
                <td style="white-space: nowrap">
                    <a class="btn btn-xs btn-primary" href="<?= h($resolverManager->resolve(['/dashboard/system/acme/domains/edit', $domain->getID()])) ?>"><?= t('Edit') ?></a>
                </td>
                <td><?= h($domain->getHostDisplayName()) ?></td>
                <?php
                if ($showPunycode) {
                    ?>
                    <td><?= h($domain->getPunycode()) ?></td>
                    <?php
                }
                ?>
                <td><?= $domain->getCertificates()->count() ?></td>
                <?php
                if ($numServers > 1) {
                    ?>
                    <td><?= h($domain->getAccount()->getServer()->getName()) ?></td>
                    <?php
                }
                if ($numAccounts > 1) {
                    ?>
                    <td><?= h($domain->getAccount()->getName()) ?></td>
                    <?php
                }
                ?>
                <td><?= h($dateHelper->formatDateTime($domain->getCreatedOn(), true, true)) ?></td>
                <td><?= h($domainService->describeChallengeType($domain)) ?></td>
            </tr>
            <?php
            }
        ?>
    </tbody>
</table>
