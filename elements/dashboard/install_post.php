<?php

use Acme\Entity\Account;
use Acme\Entity\Server;
use Concrete\Core\Form\Service\Form;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;
use Concrete\Core\User\User;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 */

$view->requireAsset('javascript', 'vue');

$app = Application::getFacadeApplication();
$token = $app->make('token');
$form = $app->make(Form::class);
$user = $app->make(User::class);
$userInfo = $user->isRegistered() ? $user->getUserInfoObject() : null;
$config = $app->make('config');
$em = $app->make(EntityManagerInterface::class);
$resolverManager = $app->make(ResolverManagerInterface::class);

$serverName = '';
$serverTermsOfServiceUrl = '';
$sampleServersList = $config->get('acme::sample_servers');
$servers = $em->getRepository(Server::class)->findBy([], ['name' => 'ASC'], 2);
switch (count($servers)) {
    case 0:
        $askServer = true;
        $askAccount = true;
        $step = 'server';
        break;
    case 1:
        $askServer = false;
        $askAccount = $em->getRepository(Account::class)->findOneBy([]) === null;
        if ($askAccount) {
            $step = 'account';
            $serverName = $servers[0]->getName();
            $serverTermsOfServiceUrl = $servers[0]->getTermsOfServiceUrl();
        } else {
            $step = 'ready';
        }
        break;
    default:
        $askServer = false;
        $askAccount = false;
        $step = 'ready';
        break;
}

?>
<div id="acme-installpost-app" style="display: none">
    <?php
    if ($askServer) {
        ?>
        <div v-if="step === steps.SERVER">
            <p><?= t('You have to choose a service provider that will generate the HTTPS certificates for you.') ?></p>
            <div id="acme-installpost-servercategories" class="panel-group" role="tablist">
                <?php
                $sampleServersCategoryNames = [
                    'production' => t('Production Servers'),
                    'staging' => t('Staging Servers'),
                    'test' => t('Test Servers'),
                ];
                $class = ' in';
                foreach ($sampleServersList as $sampleServersCategory => $sampleServers) {
                    if (empty($sampleServers)) {
                        continue;
                    }
                    ?>
                    <div class="panel panel-default">
                        <div class="panel-heading" role="tab" id="acme-installpost-servercategory-<?= $sampleServersCategory ?>-header" v-on:click="preventDefaultIfBusy">
                            <h4 class="panel-title">
                                <a role="button" data-toggle="collapse" data-parent="#acme-installpost-servercategories" href="#acme-installpost-servercategory-<?= $sampleServersCategory ?>-body">
                                    <?= h(isset($sampleServersCategoryNames[$sampleServersCategory]) ? $sampleServersCategoryNames[$sampleServersCategory] : $sampleServersCategory) ?>
                                </a>
                            </h4>
                        </div>
                        <div id="acme-installpost-servercategory-<?= $sampleServersCategory ?>-body" class="panel-collapse collapse<?= $class ?>" role="tabpanel">
                            <div class="panel-body">
                                <?php
                                foreach ($sampleServers as $sampleServerHandle => $sampleServerData) {
                                    ?>
                                    <button class="btn btn-primary btn-sm" v-bind:disabled="busy" v-on:click.prevent="<?= h('createServer(' . json_encode($sampleServersCategory) . ', ' . json_encode($sampleServerHandle) . ')') ?>">
                                        <?= h($sampleServerData['name']) ?>
                                    </button>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php
                    $class = '';
                }
                ?>
            </div>
            <a href="#" v-bind:disabled="busy" v-on:click.prevent="if (!busy) step = steps.READY" class="btn btn-sm btn-default"><?= t('Skip') ?></a>
        </div>
        <?php
    }
    ?>
    <?php
    if ($askAccount) {
        ?>
        <div v-if="step === steps.ACCOUNT">
            <p><?= t('In order to generate HTTPS certificates you need an account at the %s server.', '{{ serverName }}') ?></p>
            <p><?= t('To create it, enter the following data') ?></p>
            <div class="form-group">
                <?= $form->label('accountName', t('Account name')) ?>
                <div class="input-group">
                    <input type="text" id="accountName" v-model.trim="accountName" class="form-control ccm-input-text" v-bind:disabled="busy" />
                    <span class="input-group-addon"><i class="fa fa-asterisk"></i></span>
                </div>
            </div>
            <div class="form-group">
                <?= $form->label('accountEmail', t('Email address')) ?>
                <div class="input-group">
                    <input type="email" id="accountEmail" v-model.trim="accountEmail" class="form-control ccm-input-text" v-bind:disabled="busy" />
                    <span class="input-group-addon"><i class="fa fa-asterisk"></i></span>
                </div>
            </div>
            <div class="form-group" v-if="serverTermsOfServiceUrl">
                <?= $form->label('', t('Options')) ?>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" v-bind:value="serverTermsOfServiceUrl" v-model="accountAcceptedServerTermsOfServiceUrl" class="ccm-input-checkbox" v-bind:disabled="busy" />
                        <?= t('I accept the <a %1$s>%2$s terms of service</a>', 'v-bind:href="serverTermsOfServiceUrl" target="_blank" rel="noopener noreferrer"', '{{ serverName }}') ?>
                    </label>
                </div>
            </div>
            <div>
                <div class="pull-left">
                    <a href="#" v-bind:disabled="busy" v-on:click.prevent="if (!busy) step = steps.READY" class="btn btn-sm btn-default"><?= t('Skip') ?></a>
                </div>
                <div class="pull-right">
                    <a href="#" v-bind:disabled="busy" v-on:click.prevent="createAccount()" class="btn btn-sm btn-primary"><?= t('Create account') ?></a>
                </div>
            </div>
        </div>
        <?php
    }
    ?>
    <div v-if="step === steps.READY">
        <p><?= t('You are ready to create HTTPS certificate for your websites.') ?></p>
        <p><?= t('Go to the <a href="%s">ACME dashboard section</a> to do that.', h($resolverManager->resolve(['/dashboard/system/acme']))) ?></p>
    </div>
</div>
<script>$(document).ready(function() {
'use strict';

$('#acme-installpost-app').show();

new Vue({
    el: '#acme-installpost-app',
    data: function() {
        return {
            busy: false,
            steps: {
                SERVER: 'server',
                ACCOUNT: 'account',
                READY: 'ready',
            },
            step: <?= json_encode($step) ?>,
            serverName: <?= json_encode($serverName) ?>,
            serverTermsOfServiceUrl: <?= json_encode($serverTermsOfServiceUrl) ?>,
            accountName: <?= json_encode($user->isRegistered() ? $user->getUserName() : '') ?>,
            accountEmail: <?= json_encode($userInfo === null ? '' : $userInfo->getUserEmail()) ?>,
            accountAcceptedServerTermsOfServiceUrl: false,
        }
    },
    methods: {
        preventDefaultIfBusy: function(e) {
            if (this.busy) {
                e.preventDefault();
                e.stopPropagation();
            }
        },
        createServer: function(category, handle) {
            var my = this;
            if (my.busy) {
                return;
            }
            my.busy = true;
            $.ajax({
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $resolverManager->resolve(['/_acme_ccm/first_time_setup/server'])) ?>,
                data: {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('acme-firsttimesetup-firstserver')) ?>,
                    handle: handle,
                    category: category,
                },
            })
            .done(function(data, status, xhr) {
                ConcreteAjaxRequest.validateResponse(data, function(ok) {
                    if (ok) {
                        my.serverName = data.name;
                        my.serverTermsOfServiceUrl = data.termsOfServiceUrl;
                        my.step = my.steps.ACCOUNT;
                    }
                });
            })
            .fail(function(xhr, status, error) {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
            })
            .always(function() {
                my.busy = false;
            });
        },
        createAccount: function() {
            var my = this;
            if (my.busy) {
                return;
            }
            my.busy = true;
            $.ajax({
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $resolverManager->resolve(['/_acme_ccm/first_time_setup/account'])) ?>,
                data: {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('acme-firsttimesetup-firstaccount')) ?>,
                    name: my.accountName,
                    email: my.accountEmail,
                    email: my.accountEmail,
                    acceptedTermsOfService: my.accountAcceptedServerTermsOfServiceUrl ? my.serverTermsOfServiceUrl : '',
                },
            })
            .done(function(data, status, xhr) {
                ConcreteAjaxRequest.validateResponse(data, function(ok) {
                    if (ok) {
                        my.step = my.steps.READY;
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

});</script>
