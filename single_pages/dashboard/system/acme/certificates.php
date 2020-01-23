<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\Account[] $accounts list of accounts that own domains
 * @var Acme\Entity\Server[] $servers list of servers with accounts owning domains
 * @var Acme\Entity\Certificate $certificates
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 * @var Concrete\Core\Localization\Service\Date $dateHelper
 * @var Acme\Certificate\Renewer $renewer
 */

$numAccounts = count($accounts);

if ($numAccounts === 0) {
    ?>
    <div class="alert alert-info">
        <?= t("There's no domain defined.") ?><br />
        <?= t('You can define the domains %shere%s.', '<a href="' . h($resolverManager->resolve(['/dashboard/system/acme/domains'])) . '">', '</a>') ?>
    </div>
    <?php
    return;
}

$numServers = count($servers);
?>
<div class="ccm-dashboard-header-buttons col-md-4 acme-hide-loading hide">
    <div class="input-group">
        <input type="search" id="acme-filter" placeholder="<?= t('Search') ?>" class="form-control" />
        <div class="input-group-btn">
            <?php
            if ($numAccounts === 1) {
                ?>
                <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/certificates/edit', 'new', $accounts[0]->getID()])) ?>" class="btn btn-primary"><?= t('Add certificate') ?></a>
                <?php
            } else {
                ?>
                <button class="btn btn-primary dropdown-toggle" data-toggle="dropdown">
                    <?= t('Add certificate') ?>
                    <span class="caret"></span>
                </button>
                <ul class="dropdown-menu">
                    <?php
                    if ($numServers === 1) {
                        foreach ($accounts as $account) {
                            ?>
                            <li>
                                <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/certificates/edit', 'new', $account->getID()])) ?>">
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
                        foreach ($servers as $server) {
                            ?>
                            <li class="dropdown-header"><?= h($server->getName())?></li>
                            <?php
                            foreach ($accounts as $account) {
                                if ($account->getServer() !== $server) {
                                    continue;
                                }
                                ?>
                                <li>
                                    <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/certificates/edit', 'new', $account->getID()])) ?>">
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
                <?php
            }
            ?>
        </div>
    </div>
</div>

<?php
if ($certificates === []) {
    ?>
    <div class="alert alert-info">
        <?= t('No certificate has been defined.') ?>
    </div>
    <?php
    return;
}
$showAccount = $numAccounts > 1;
$showServer = $numServers > 1;
?>
<table class="table table-striped table-condensed acme-hide-loading hide" id="acme-list">
    <col width="1" />
    <thead>
        <tr>
            <th></th>
            <?php
            if ($showServer) {
                ?>
                <th><?= t('Server') ?></th>
                <?php
            }
            if ($showAccount) {
                ?>
                <th><?= t('Account') ?></th>
                <?php
            }
            ?>
            <th><?= t('Domains') ?></th>
            <th><?= t('Valid from') ?></th>
            <th><?= t('Valid to') ?></th>
            <th><?= t('Issuer') ?></th>
            <th><?= t('Actions') ?></th>
            <th><?= t('Operation') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($certificates as $certificate) {
            $info = $certificate->getCertificateInfo();
            $numActions = $certificate->getActions()->count();
            $domainNames = [];
            foreach ($certificate->getDomains() as $certificateDomain) {
                $domainNames[] = $certificateDomain->getDomain()->getHostDisplayName();
            }
            ?>
            <tr data-acme-domain-names="<?= h(mb_strtolower(implode(' ', $domainNames)))?>">
                <td>
                    <a class="btn btn-xs btn-primary" href="<?= h($resolverManager->resolve(['/dashboard/system/acme/certificates/edit', $certificate->getID()])) ?>"><?php
                    if ($certificate->getCsr() === '' && $certificate->getOngoingOrder() === null) {
                        echo t('Edit');
                    } else {
                        echo t('Details');
                    }
                    ?></a>
                </td>
                <?php
                if ($showServer) {
                    ?>
                    <th><?= h($certificate->getAccount()->getServer()->getName()) ?></th>
                    <?php
                }
                if ($showAccount) {
                    ?>
                    <th><?= h($certificate->getAccount()->getName()) ?></th>
                    <?php
                }
                ?>
                <td>
                    <?php
                    foreach ($certificate->getDomains() as $certificateDomain) {
                        if ($certificateDomain->isPrimary()) {
                            echo '<strong>';
                        }
                        echo h($certificateDomain->getDomain()->getHostDisplayName());
                        if ($certificateDomain->isPrimary()) {
                            echo '</strong>';
                        }
                        echo '<br />';
                    }
                    ?>
                </td>
                <td><?= $info === null ? '' : h($dateHelper->formatDateTime($info->getStartDate(), true, true)) ?></td>
                <td><?= $info === null ? '' : h($dateHelper->formatDateTime($info->getEndDate(), true, true)) ?></td>
                <td><?= $info === null ? '' : h($info->getIssuerName()) ?></td>
                <td>
                    <a class="btn btn-xs btn-info" href="<?= h($resolverManager->resolve(['/dashboard/system/acme/certificates/actions', $certificate->getID()])) ?>">
                        <?= t('Actions')?>
                        <span class="badge"><?= $numActions ?></span>
                    </a>
                </td>
                <td>
                    <a class="btn btn-xs btn-primary" href="<?= h($resolverManager->resolve(['/dashboard/system/acme/certificates/operate', $certificate->getID()])) ?>">
                        <?php
                        switch ($renewer->getCertificateState($certificate)) {
                            case $renewer::CERTIFICATESTATE_GOOD:
                            case $renewer::CERTIFICATESTATE_RUNACTIONS:
                                echo t('Run actions');
                                break;
                            case $renewer::CERTIFICATESTATE_SHOULDBERENEWED:
                            case $renewer::CERTIFICATESTATE_EXPIRED:
                                echo t('Renew certificate');
                                break;
                            case $renewer::CERTIFICATESTATE_MUSTBEGENERATED:
                            default:
                                echo t('Generate certificate');
                                break;
                        }
                        ?>
                    </a>
                </td>
            </tr>
            <?php
            }
        ?>
    </tbody>
</table>
<script>
$(document).ready(function() {
    var $search = $('#acme-filter'),
        $table = $('#acme-list'),
        $rows = $('#acme-list').find('>tbody>tr'),
        persistentSearch = (function() {
            var LS = window.localStorage && window.localStorage.getItem && window.localStorage.setItem && window.localStorage.removeItem ? window.localStorage : null,
                KEY = 'acme-certificates-list-search'
            ;
            return {
                get: function() {
                    return LS === null ? '' : (LS.getItem(KEY) || '');
                },
                set: function(value) {
                    if (LS === null) {
                        return;
                    }
                    if (typeof value !== 'string' || (value = $.trim(value)) == '') {
                        LS.removeItem(KEY);
                    } else {
                        LS.setItem(KEY, value);
                    }
                }
            };
        })(),
        currentSearch = null;

    var applyFilter = (function() {
        var prevWhat = null;
        function getKeywords(what) {
            if (typeof what !== 'string') {
                return [];
            }
            var result = [];
            $.each($.trim(what).toLowerCase().split(/\s+/), function (_, word) {
                if (word !== '' && result.indexOf(word) < 0) {
                    result.push(word);
                }
            });
            return result;
        }
        return function(what) {
            if (what === prevWhat) {
                return;
            }
            prevWhat = what;
            var keywords = getKeywords(what);
            persistentSearch.set(what);
            $rows.each(function() {
                var $row = $(this),
                    hide = false;
                if (keywords.length > 0) {
                    var domainNames = $row.data('acme-domain-names');
                    $.each(keywords, function(_, word) {
                        if (domainNames.indexOf(word) < 0) {
                            hide = true;
                            return false;
                        }
                    });
                }
                $row.toggleClass('hide', hide);
            });
        };
    })();

    applyFilter(persistentSearch.get());

    $search
        .on('input', function() {
           applyFilter($search.val());
        })
        .val(persistentSearch.get());
    ;

    $('.acme-hide-loading').removeClass('hide');

    if ($search.val().length > 0) {
        $search.focus();
    }
});
</script>
