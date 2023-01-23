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
 * @var Acme\Service\UI $ui
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
<div class="ccm-dashboard-header-buttons acme-hide-loading <?= $ui->displayNone ?><?= $ui->majorVersion <= 8 ? ' col-md-4' : '' ?>">
    <div>
        <div class="input-group">
            <input type="search" id="acme-filter" placeholder="<?= t('Search') ?>" class="form-control" />
            <div class="input-group-btn">
                <?php
                if ($numAccounts === 1) {
                    $account = $serversWithAccounts[0]->getAccounts()->first();
                    ?>
                    <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/domains/edit', 'new', $account->getID()])) ?>" class="btn btn-primary"><?= t('Add domain') ?></a>
                    <?php
                } else {
                    ?>
                    <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" data-toggle="dropdown">
                        <?= t('Add domain') ?>
                        <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu">
                        <?php
                        if (count($serversWithAccounts) === 1) {
                            foreach ($serversWithAccounts[0]->getAccounts() as $account) {
                                ?>
                                <li>
                                    <a class="dropdown-item" href="<?= h($resolverManager->resolve(['/dashboard/system/acme/domains/edit', 'new', $account->getID()])) ?>">
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
                                <li><span class="dropdown-header"><?= h($server->getName())?></span></li>
                                <?php
                                foreach ($server->getAccounts() as $account) {
                                    ?>
                                    <li>
                                        <a class="dropdown-item" href="<?= h($resolverManager->resolve(['/dashboard/system/acme/domains/edit', 'new', $account->getID()])) ?>">
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
</div>
<div>
    <?php
    if ($domains === []) {
        ?>
        <div class="alert alert-info">
            <?= t('No domain has been defined.') ?>
        </div>
        <?php
    } else {
        $showPunycode = false;
        foreach ($domains as $domain) {
            if ($domain->getHostname() !== $domain->getPunycode()) {
                $showPunycode = true;
                break;
            }
        }
        ?>
        <table class="table table-striped table-condensed acme-hide-loading <?= $ui->displayNone ?>" id="acme-list">
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
                    <tr data-acme-domain-name="<?= h(mb_strtolower($domain->getHostDisplayName())) ?>">
                        <td style="white-space: nowrap">
                            <a class="btn btn-sm btn-primary" href="<?= h($resolverManager->resolve(['/dashboard/system/acme/domains/edit', $domain->getID()])) ?>"><?= t('Edit') ?></a>
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
        <?php
    }
    ?>
</div>
<script>
$(document).ready(function() {
    var $search = $('#acme-filter'),
        $table = $('#acme-list'),
        $rows = $('#acme-list').find('>tbody>tr'),
        persistentSearch = (function() {
            var LS = window.localStorage && window.localStorage.getItem && window.localStorage.setItem && window.localStorage.removeItem ? window.localStorage : null,
                KEY = 'acme-domains-list-search'
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
                    var domainName = $row.data('acme-domain-name');
                    $.each(keywords, function(_, word) {
                        if (domainName.indexOf(word) < 0) {
                            hide = true;
                            return false;
                        }
                    });
                }
                $row.toggleClass(<?= json_encode($ui->displayNone) ?>, hide);
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

    $('.acme-hide-loading').removeClass(<?= json_encode($ui->displayNone) ?>);

    if ($search.val().length > 0) {
        $search.focus();
    }
});
</script>
