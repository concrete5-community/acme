<?php
defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\Domain $domain
 * @var Acme\DomainService $domainService
 * @var Acme\ChallengeType\ChallengeTypeInterface[] $challengeTypes
 * @var Concrete\Core\Filesystem\ElementManager $elementManager
 * @var Concrete\Core\Page\Page $page
 * @var array|null $deviation
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 * @var Concrete\Core\Localization\Service\Date $dateHelper
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Page\View\PageView $view
 */

if ($domain->getID() !== null) {
    ?>
    <form method="POST" action="<?= h($view->action('delete', $domain->getID())) ?>" id="acme-domain-delete" class="hide">
        <?php $token->output('acme-domain-delete-' . $domain->getID()) ?>
    </form>
    <?php
}
$numCertificates = $domain->getCertificates()->count();
?>

<form method="POST" action="<?= h($view->action('submit', $domain->getID() ?: 'new', $domain->getAccount()->getID())) ?>">
    <?php
    $token->output('acme-domain-edit-' . ($domain->getID() ?: 'new') . '-' . $domain->getAccount()->getID());
    ?>

    <div class="row">
        <?php
        $md = $domain->getID() ? '4' : '6';
        ?>
        <div class="col-md-<?= $md ?>">
            <div class="form-group">
                <?= $form->label('', 'ACME server') ?>
                <div class="form-control"><?= h($domain->getAccount()->getServer()->getName()) ?></div>
            </div>
        </div>
        <div class="col-md-<?= $md ?>">
            <div class="form-group">
                <?= $form->label('', 'Associated account') ?>
                <div class="form-control"><?= h($domain->getAccount()->getName()) ?></div>
            </div>
        </div>
        <?php
        if ($domain->getID() !== null) {
            ?>
            <div class="col-md-4">
                <div class="form-group">
                    <?= $form->label('', 'Created on') ?>
                    <div class="form-control"><?= h($dateHelper->formatDateTime($domain->getCreatedOn(), true, true)) ?></div>
                </div>
            </div>
            <?php
        }
        ?>
    </div>

    <div class="form-group">
        <?= $form->label('hostname', t('Domain name')) ?>
        <div class="input-group">
            <?= $form->text('hostname', $domain->getHostname(), ['maxlength' => '255', 'required' => 'required'] + ($numCertificates === 0 ? [] : ['readonly' => 'readonly'])) ?>
            <span class="input-group-addon"><i class="fa fa-asterisk"></i></span>
        </div>
        <div class="text-muted small">
            <?php
            if ($numCertificates === 0) {
                echo t(
                    'You can specify domain names with international characters (for example %1$s), as well as wildcards (for example %2$s).',
                    '<code>www.schloß.com</code>',
                    '<code>*.example.org</code>'
                );
            } else {
                echo t2(
                    "It's not possible to change the domain name since it's used in %s certificate",
                    "It's not possible to change the domain name since it's used in %s certificates",
                    $numCertificates
                );
            }
            ?>
        </div>
    </div>

    <div class="form-group" id="acme-domainedit-deviation">
        <?= $form->label('', t('Deviation')) ?>
        <div class="form-control">
            <template v-if="ready">
                <template v-if="deviation === null">
                    <?= t('There are no deviation problems for <code>%s</code>', '{{ hostname }}') ?>
                </template>
                <template v-else-if="deviation.name === deviation.punycode">
                    <?= t(
                        'For security reasons, if you register the domain %1$s you should also register %2$s',
                        '<code>{{ hostname }}</code>',
                        '<code>{{ deviation.name }}</code>'
                    ) ?>
                </template>
                <template v-else>
                    <?= t(
                        'For security reasons, if you register the domain %1$s you should also register %2$s',
                        '<code>{{ hostname }}</code>',
                        '<code>{{ deviation ? deviation.name : false }}</code>'
                    ) ?>
                </template>
            </template>
        </div>
    </div>

    <div class="form-group">
        <?= $form->label('challengetype', t('Authorization type')) ?>
        <div class="input-group">
            <?php
            $challengeTypeOptions = [];
            foreach ($challengeTypes as $challengeType) {
                $challengeTypeOptions[$challengeType->getHandle()] = $challengeType->getName();
            }
            if (!isset($challengeTypeOptions[$domain->getChallengeTypeHandle()])) {
                $challengeTypeOptions = ['' => ''] + $challengeTypeOptions;
            }
            ?>
            <?= $form->select('challengetype', $challengeTypeOptions, $domain->getChallengeTypeHandle(), ['required' => 'required']) ?>
            <span class="input-group-addon"><i class="fa fa-asterisk"></i></span>
        </div>
    </div>

    <?php
    foreach ($challengeTypes as $challengeType) {
        ?>
        <div class="acme-challengetypeelement alert alert-info" id="acme-challengetypeelement-<?= h($challengeType->getHandle()) ?>"<?= $challengeType->getHandle() === $domain->getChallengeTypeHandle() ? '' : ' style="display: none"'?>>
            <?php
            $challengeType->getDomainConfigurationElement($domain, $elementManager, $page)->render();
            ?>
        </div>
        <?php
    }
    ?>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/domains'])) ?>" class="btn btn-default pull-left"><?= t('Cancel') ?></a>
            <div class="pull-right">
                <?php
                if ($domain->getID() !== null) {
                    ?>
                    <a href="#" id="acme-btn-delete" class="btn btn-danger"><?= t('Delete') ?></a>
                    <?php
                }
                ?>
                <input type="submit" class="btn btn-primary" value="<?= t('Save') ?>" />
            </div>
        </div>
    </div>

</form>

<script>$(document).ready(function() {
'use strict';

var vue = new Vue({
    el: '#acme-domainedit-deviation',
    data: function() {
        var data = {
            settingHostname: null,
            hostname: <?= json_encode($domain->getHostname()) ?>,
            deviations: {
                <?= json_encode($domain->getHostname()) ?>: <?= json_encode($deviation) ?>,
            },
        }
        return data;
    },
    computed: {
        ready: function() {
            return this.hostname && this.deviations.hasOwnProperty(this.hostname) && typeof this.deviations[this.hostname] !== 'string';
        },
        deviation: function() {
            return this.ready ? this.deviations[this.hostname] : null;
        },
    },
    methods: {
        setHostname: function(hostname) {
            var my = this;
            hostname = hostname.replace(/^\s+|\s+/g, '').replace(/^\*\./, '');
            if (my.hostname === hostname || my.settingHostname === hostname) {
                return;
            }
            if (my.deviations.hasOwnProperty(hostname)) {
                my.settingHostname = null;
                my.hostname = hostname;
                return;
            }
            if (hostname === '') {
                my.settingHostname = null;
                my.deviations[''] = null;
                my.hostname = hostname;
                return;
            }
            my.hostname = '';
            my.settingHostname = hostname;
            setTimeout(function() {
                if (my.settingHostname !== hostname) {
                    return;
                }
                $.ajax({
                    dataType: 'json',
                    method: 'POST',
                    url: <?= json_encode((string) $view->action('check_deviation')) ?>,
                    data: {
                        <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('acme-check-deviation')) ?>,
                        hostname: hostname,
                    }
                })
                .done(function(data, status, xhr) {
                    if (data.error) {
                        return;
                    }
                    my.deviations[hostname] = data || null;
                    if (my.settingHostname === hostname) {
                        my.hostname = hostname;
                    }
                })
                .always(function() {
                    if (my.settingHostname === hostname) {
                        my.settingHostname = null;
                    }
                });
            }, 500);
        }
    },
});

$('#hostname').on('change keyup', function() {
    vue.setHostname(this.value);
});

vue.setHostname($('#hostname').val());

$('#challengetype')
    .on('change', function() {
        var challengeType = this.value;
        $('.acme-challengetypeelement').hide();
        if (challengeType) {
            $('#acme-challengetypeelement-' + challengeType).show();
        }
    })
    .trigger('change')
;
<?php
if ($domain->getID() !== null) {
    ?>
    var alreadyDeleted = false;
    $('a#acme-btn-delete').on('click', function(e) {
        e.preventDefault();
        if (window.confirm(<?= json_encode(t('Are you sure you want to delete this domain?')) ?>)) {
            $('form#acme-domain-delete').submit();
        }
    });
    <?php
}
?>

});
</script>
