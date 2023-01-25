<?php

use Acme\Entity\AuthorizationChallenge;
use Acme\Entity\Order;
use Psr\Log\LogLevel;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\Certificate $certificate
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Localization\Localization $localization
 * @var Acme\Service\UI $ui
 */
?>
<div class="ccm-dashboard-header-buttons">
    <div class="btn-group <?= $ui->displayNone ?>" id="acme-certificate-operate-actions">
        <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" data-toggle="dropdown" v-bind:disabled="busy || !hasCertificateInfo">
            <?= t('Advanced') ?>
            <span class="caret"></span>
        </button>
        <ul class="dropdown-menu <?= $ui->dropdownMenuAlignedEnd ?>">
            <li v-bind:class="{disabled: !hasCertificateInfo}">
                <a class="dropdown-item" href="#" v-on:click.prevent="forceRenew"><?= t('Force renewal of certificate ')?></a>
            </li>
            <li<?= (0 && $certificate->getActions()->isEmpty()) ? ' class="disabled"' : '' ?>>
                <a class="dropdown-item" href="#"<?= (0 && $certificate->getActions()->isEmpty()) ? ' onclick="return false"' : ' v-on:click.prevent="forceActions"' ?>><?= t('Force re-execution of actions')?></a>
            </li>
        </ul>
    </div>
</div>

<div><div class="<?= $ui->displayNone ?>" id="acme-certificate-operate">
    <div class="row">
        <div class="col-md-6">
            <fieldset>
                <legend><?= t('Current certificate') ?></legend>
                <div v-if="certificateInfo === null" class="alert alert-warning">
                    <?= t('No current certificate') ?>
                </div>
                <table v-else class="table table-striped table-condensed">
                    <tbody>
                        <tr>
                            <th><?= t('Domains included in the certificate') ?></th>
                            <td>
                                <ol>
                                    <li v-for="certifiedDomain in certificateInfo.certifiedDomains">
                                        {{ certifiedDomain }}
                                    </li>
                                </ol>
                            </td>
                        </tr>
                        <tr>
                            <th><?= t('Valid from') ?></th>
                            <td>{{ formatTimestamp(certificateInfo.startDate) }}</td>
                        </tr>
                        <tr>
                            <th><?= t('Valid to') ?></th>
                            <td>{{ formatTimestamp(certificateInfo.endDate) }}</td>
                        </tr>
                        <tr>
                            <th><?= t('Issued by') ?></th>
                            <td>{{ certificateInfo.issuerName }}</td>
                        </tr>
                        <tr>
                            <th></th>
                            <td><button class="btn <?= $ui->defaultButton ?>" v-on:click.prevent="checkRevocation()" v-bind:disabled="busy"><?= t('Check revocation') ?></button></td>
                        </tr>
                    </tbody>
                </table>
            </fieldset>
        </div>
        <div class="col-md-6" v-if="order !== null">
            <fieldset>
                <legend><?= t('Certificate generation') ?></legend>
                <div><strong>{{ getOrderStatusName(order.type, order.status) }}.</strong></div>
                <ul v-if="order.authorizationChallenges.length !== 0">
                    <li v-for="authorizationChallenge in order.authorizationChallenges">
                        <strong><?= t('Authorization for %s', '{{ authorizationChallenge.domain }}')?></strong>: {{ getAuthorizationStatusName(authorizationChallenge.authorizationStatus) }}<br />
                        <?= t('Challenge status: %s', '{{ getChallengeStatusName(authorizationChallenge.challengeStatus) }}') ?>
                        <div v-if="authorizationChallenge.challengeError !== ''" class="alert alert-danger">
                            {{ authorizationChallenge.challengeError }}
                        </div>
                    </li>
                </ul>
            </fieldset>
        </div>
    </div>

    <fieldset v-if="messages.length !== 0">
        <legend><?= t('Progress messages') ?></legend>
        <div v-for="message in messages" v-bind:class="message.cssClass" style="white-space: pre-wrap">{{ message.message }}</div>
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/certificates'])) ?>" class="btn <?= $ui->defaultButton ?> <?= $ui->floatStart ?>" v-bind:disabled="busy">
                {{ step === steps.DONE ? <?= json_encode(t('Back')) ?> : <?= json_encode(t('Cancel')) ?> }}
            </a>
            <div class="<?= $ui->floatEnd ?>">
                <button v-if="step === steps.INITIAL" class="btn btn-primary" v-on:click.prevent="setStep(steps.PROCESSING)">
                    <?= t('Start') ?>
                </button>
                <button v-if="step === steps.PROCESSING" class="btn btn-primary disabled" onclick="return false">
                    <i class="<?= $ui->faRefreshSpinning ?>"></i> <?= t('Processing') ?>
                </button>
                <button v-else-if="step === steps.DONE" class="btn btn-primary" v-on:click.prevent="startOver()">
                    <?= t('Start over') ?>
                </button>
            </div>
        </div>
    </div>

</div></div>

<script>$(document).ready(function() { setTimeout(function() {
'use strict';

$('#acme-certificate-operate-actions, #acme-certificate-operate').removeClass(<?= json_encode($ui->displayNone) ?>);

var Bridge = {
    menu: null,
    main: null,
    sync: function() {
        if (this.menu === null || this.main === null) {
            return;
        }
        this.menu.busy = this.main.busy;
        this.menu.hasCertificateInfo = this.main.certificateInfo !== null;
    },
};

new Vue({
    el: '#acme-certificate-operate-actions',
    data: function() {
        return {
            busy: true,
            hasCertificateInfo: false,
        };
    },
    mounted: function() {
        Bridge.menu = this;
        Bridge.sync();
    },
    methods: {
        forceRenew: function() {
            if (Bridge.main !== null && !this.busy && this.hasCertificateInfo) {
                Bridge.main.startOver({forceRenew: 1});
            }
        },
        forceActions: function(options) {
            if (Bridge.main !== null && !this.busy && this.hasCertificateInfo) {
                Bridge.main.startOver({forceActions: 1});
            }
        },
    },
});

new Vue({
    el: '#acme-certificate-operate',
    data: function() {
        var data = {
            steps: {
                INITIAL: 'initial',
                PROCESSING: 'processing',
                DONE: 'done',
            },
            busy: false,
            certificateInfo: <?= json_encode($certificate->getCertificateInfo()) ?>,
            order: null,
            firstStepOptions: {},
            messages: [],
        };
        data.step = data.steps.INITIAL;
        return data;
    },
    mounted: function() {
        var my = this;
        Bridge.main = my;
        Bridge.sync();
        $(window).on('beforeunload', function() {
            if (my.busy) {
                return 'busy';
            }
        });
        my.setStep(my.steps.PROCESSING);
    },
    watch: {
        busy: function() {
            Bridge.sync();
        },
        certificateInfo: function() {
            Bridge.sync();
        },
    },
    methods: {
        formatTimestamp: function(timestamp) {
            if (!timestamp || isNaN(timestamp)) {
                return '';
            }
            var date = new Date(timestamp * 1000);
            if (!date.toLocaleString) {
                return date.toString();
            }
            return date.toLocaleString(<?= json_encode(str_replace('_', '-', $localization->getLocale())) ?>, {dateStyle: 'medium', timeStyle: 'short'});
        },
        setStep: function(step) {
            var oldStep = this.step;
            this.step = step;
            switch (step) {
                case this.steps.PROCESSING:
                    this.busy = true;
                    break;
                default:
                    this.busy = false;
                    break;
            }
            switch (step) {
                case this.steps.PROCESSING:
                    if (oldStep === this.steps.INITIAL || oldStep === this.steps.DONE) {
                        this.continueProcessing(true);
                    }
                    break;
            }
        },
        continueProcessing: function (firstStep) {
            var my = this;
            if (my.step !== my.steps.PROCESSING) {
                return;
            }
            var sendData = $.extend(
                {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('acme-certificate-nextstep-' . $certificate->getID())) ?>,
                },
                my.firstStepOptions
            );
            my.firstStepOptions = {};
            $.ajax({
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action(['next_step', $certificate->getID()])) ?>,
                data: sendData,
            })
            .done(function(data, status, xhr) {
                ConcreteAjaxRequest.validateResponse(data, function(ok) {
                    if (!ok) {
                        my.setStep(my.steps.DONE);
                        return;
                    }
                    for (var i = 0; i < data.messages.length; i++) {
                        data.messages[i].cssClass = my.getMessageClassName(data.messages[i]);
                        my.messages.push(data.messages[i]);
                    }
                    if (data.hasOwnProperty('certificateInfo')) {
                        my.certificateInfo = data.certificateInfo;
                        my.certificateUpdated = true;
                    }
                    if (data.hasOwnProperty('order')) {
                        my.order = data.order;
                    }
                    if (typeof data.nextStepAfter === 'number') {
                        setTimeout(
                            function() {
                                my.continueProcessing(false);
                            },
                            data.nextStepAfter * 1000
                        );
                    } else {
                        my.setStep(my.steps.DONE);
                    }
                });
            })
            .fail(function(xhr, status, error) {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
                my.setStep(my.steps.DONE);
            });
        },
        getOrderStatusName: function(type, status) {
            var map = <?= json_encode([
                Order::TYPE_AUTHORIZATION => [
                    Order::STATUS_PENDING => t('The ACME server is authorizing the domains'),
                    Order::STATUS_READY => t('The ACME server authorized the domains'),
                    Order::STATUS_INVALID => t('The ACME server failed to validate the domains'),
                ],
                Order::TYPE_ORDER => [
                    Order::STATUS_PENDING => t('The ACME server is authorizing the domains'),
                    Order::STATUS_READY => t("The ACME server authorized the domains and it's ready to generate the certificate"),
                    Order::STATUS_PROCESSING => t('The ACME server is generating the certificate'),
                    Order::STATUS_VALID => t('The ACME server generated the certificate'),
                    Order::STATUS_INVALID => t('The ACME server failed to validate the domains, or the certificate generation failed'),
                ],
            ]) ?>;
            return map.hasOwnProperty(type) && map[type].hasOwnProperty(status) ? map[type][status] : <?= json_encode(t('Unknown')) ?>;
        },
        getAuthorizationStatusName: function(status) {
            var map = <?= json_encode([
                AuthorizationChallenge::AUTHORIZATIONSTATUS_PENDING => t('pending'),
                AuthorizationChallenge::AUTHORIZATIONSTATUS_VALID => t('authorization confirmed'),
                AuthorizationChallenge::AUTHORIZATIONSTATUS_INVALID => t('authorization failed'),
                AuthorizationChallenge::AUTHORIZATIONSTATUS_EXPIRED => t('authorization expired'),
                AuthorizationChallenge::AUTHORIZATIONSTATUS_DEACTIVATED => t('authorization deactivated by the client'),
                AuthorizationChallenge::AUTHORIZATIONSTATUS_REVOKED => t('authorization revoked by the server'),
            ]) ?>;
            return map.hasOwnProperty(status) ? map[status] : <?= json_encode(t('Unknown')) ?>;
        },
        getChallengeStatusName: function(status) {
            var map = <?= json_encode([
                AuthorizationChallenge::CHALLENGESTATUS_PENDING => t('pending'),
                AuthorizationChallenge::CHALLENGESTATUS_PROCESSING => t('the ACME server is checking the domain'),
                AuthorizationChallenge::CHALLENGESTATUS_VALID => t('the ACME server confirmed the authorization challenge'),
                AuthorizationChallenge::CHALLENGESTATUS_INVALID => t('the authorization challenge failed'),
            ]) ?>;
            return map.hasOwnProperty(status) ? map[status] : <?= json_encode(t('Unknown')) ?>;
        },
        getMessageClassName: function(message) {
            var levelsMap = <?= json_encode([
                LogLevel::EMERGENCY => 'alert alert-danger',
                LogLevel::ALERT => 'alert alert-danger',
                LogLevel::CRITICAL => 'alert alert-danger',
                LogLevel::ERROR => 'alert alert-danger',
                LogLevel::WARNING => 'alert alert-warning',
                LogLevel::NOTICE => 'alert alert-warning',
                LogLevel::INFO => 'alert alert-info',
                LogLevel::DEBUG => $ui->majorVersion >= 9 ? 'alert alert-secondary' : 'alert alert-info',
            ]) ?>;
            if (levelsMap.hasOwnProperty(message.level)) {
                return levelsMap[message.level];
            }
            return 'alert alert-danger';
        },
        startOver: function(options) {
            if (this.busy || this.step !== this.steps.DONE) {
                return;
            }
            this.order = null;
            if (this.messages.length > 0) {
                this.messages.splice(0, this.messages.length);
            }
            this.firstStepOptions = $.extend(true, {}, options || {});
            this.setStep(this.steps.PROCESSING);
        },
        checkRevocation: function() {
            var my = this;
            if (my.busy) {
                return;
            }
            my.busy = true;
            $.ajax({
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action(['check_revocation', $certificate->getID()])) ?>,
                data: {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('acme-certificate-checkrevocation-' . $certificate->getID())) ?>,
                },
            })
            .done(function(data, status, xhr) {
                ConcreteAjaxRequest.validateResponse(data, function(ok) {
                    if (!ok) {
                        return;
                    }
                    if (data.revoked === false) {
                        ConcreteAlert.info({title: <?= json_encode(t('Certificate revokation')) ?>, message: <?= json_encode(t('The certificate is not revoked.')) ?>});
                    } else if (data.revoked === true) {
                        ConcreteAlert.error({title: <?= json_encode(t('Certificate revokation')) ?>, message: <?= json_encode(t('The certificate has been REVOKED on %s.')) ?>.replace(/\%s/, data.revokedOn)});
                    } else {
                        ConcreteAlert.error({title: <?= json_encode(t('Certificate revokation')) ?>, message: <?= json_encode(t('It was not possible to determine if the certificate is revoked.')) ?>});
                    }
                });
            })
            .fail(function(xhr, status, error) {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
            })
            .always(function() {
                my.busy = false;
            });
        }
    },
});

}, 100); });
</script>
