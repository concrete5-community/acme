<?php

use Acme\Entity\CertificateAction;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\Certificate $certificate
 * @var Acme\Entity\CertificateAction[] $actions
 * @var Acme\Entity\RemoteServer[] $remoteServer
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Page\View\PageView $view
 */

?>

<div id="acme-certificate-actions" class="hide">

    <div class="ccm-dashboard-header-buttons">
        <button class="btn btn-primary" v-on:click.prevent="addNewAction" v-bind:disabled="busy"><?= t('New action') ?></button>
    </div>

    <div v-for="action in actions" v-if="action !== undefined" class="alert alert-info">
        <table class="table table-condensed">
            <tbody>
                <tr>
                    <th><?= t('Server') ?></th>
                    <td>
                        <select v-model="action.remoteServer">
                            <option value="."><?= t('This server') ?></option>
                            <?php
                            foreach ($remoteServers as $remoteServer) {
                                ?>
                                <option value="<?= $remoteServer->getID() ?>"><?= h($remoteServer->getName()) ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><input type="checkbox" v-model="action.savePrivateKey" /> <?= t('Save private key') ?></label></th>
                    <td>
                        <input type="text" v-model.trim="action.savePrivateKeyTo" v-bind:disabled="!action.savePrivateKey" />
                        <div class="small text-muted">
                            <?= t(
                                'Useful for %1$s (%2$s directive) and %3$s (%4$s directive)',
                                '<strong>Apache</strong>', '<code>SSLCertificateKeyFile</code>',
                                '<strong>Nginx</strong>', '<code>ssl_certificate_key</code>'
                            ) ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label><input type="checkbox" v-model="action.saveCertificate" /> <?= t('Save certificate') ?></label></th>
                    <td>
                        <input type="text" v-model.trim="action.saveCertificateTo" v-bind:disabled="!action.saveCertificate" />
                        <div class="small text-muted">
                            <?= t(
                                'Useful for %1$s (%2$s directive)',
                                '<strong>Apache &lt; 2.4.8</strong>', '<code>SSLCertificateFile</code>'
                            ) ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label><input type="checkbox" v-model="action.saveIssuerCertificate"/> <?= t('Save the issuer certificate') ?></label></th>
                    <td>
                        <input type="text" v-model.trim="action.saveIssuerCertificateTo" v-bind:disabled="!action.saveIssuerCertificate" />
                        <div class="small text-muted">
                            <?= t(
                                'Useful for %1$s (%2$s directive) and %3$s (%4$s directive)',
                                '<strong>Apache &lt; 2.4.8</strong>', '<code>SSLCertificateChainFile</code>',
                                '<strong>Nginx &ge; 1.3.7</strong>', '<code>ssl_trusted_certificate</code>'
                            ) ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label><input type="checkbox" v-model="action.saveCertificateWithIssuer"/> <?= t('Save certificate and issuer') ?></label></th>
                    <td>
                        <input type="text" v-model.trim="action.saveCertificateWithIssuerTo" v-bind:disabled="!action.saveCertificateWithIssuer" />
                        <div class="small text-muted">
                            <?= t(
                                'Useful for %1$s (%2$s directive) and %3$s (%4$s directive)',
                                '<strong>Apache &ge; 2.4.8</strong>', '<code>SSLCertificateFile</code>',
                                '<strong>Nginx</strong>', '<code>ssl_certificate</code>'
                            ) ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label><input type="checkbox" v-model="action.executeCommand"/> <?= t('Execute command') ?></label></th>
                    <td>
                        <input type="text" v-model.trim="action.commandToExecute" v-bind:disabled="!action.executeCommand" />
                        <div class="small text-muted">
                            <?= t(
                                'Useful to reload the web server configuration after updating the certificate (for example: %s)',
                                '<code>service apache2 reload</code>'
                            ) ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="text-right">
            <button class="btn btn-danger" v-on:click.prevent="askRemoveAction(action)" v-bind:disabled="busy"><?= t('Delete') ?></button>
            <button class="btn btn-primary" v-on:click.prevent="saveAction(action)" v-bind:disabled="busy || !action.dirty"><?= t('Save') ?></button>
        </div>
    </div>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/certificates'])) ?>" class="btn btn-default pull-left"><?= t('Back') ?></a>
            <div class="pull-right">
            </div>
        </div>
    </div>
</div>

<script>$(document).ready(function() {
'use strict';

$('#acme-certificate-actions').removeClass('hide');

function DomainAction(data)
{
    var my = this;
    my.setData(data);
    Object.defineProperties(
        this,
        {
            isNew: {
                get: function () {
                    return my._savedData === null;
                },
            },
            dirty: {
                get: function () {
                    if (my.isNew) {
                        return true;
                    }
                    for (var fieldIndex = 0; fieldIndex < my._fieldNames.length; fieldIndex++) {
                        var fieldName = my._fieldNames[fieldIndex];
                        if (my._savedData[fieldName] !== my[fieldName]) {
                            return true;
                        }
                    }
                    return false;
                },
            },
        }
    );
}
DomainAction.prototype = {
    setData: function(data) {
        this._fieldNames = [];
        data = $.extend(true, {}, data);
        for (var fieldName in data) {
            if (!data.hasOwnProperty(fieldName)) {
                continue;
            }
            var value = data[fieldName];
            this._fieldNames.push(fieldName);
            this[fieldName] = value;
        }
        this._savedData = data.id === null ? null : data;
    },
    getData: function() {
        var my = this,
            result = {};
        my._fieldNames.forEach(function(fieldName) {
            var value = my[fieldName];
            result[fieldName] = my[fieldName];
        });
        return result;
    }
};

new Vue({
    el: '#acme-certificate-actions',
    data: function() {
        var data = {
            busy: false,
            actions: [],
        };
        <?= json_encode($actions) ?>.forEach(function(actionData) {
            data.actions[actionData.position] = new DomainAction(actionData);
        });
        return data;
    },
    mounted: function() {
        var my = this;
        $(window).on('beforeunload', function() {
            if (my.busy) {
                return 'busy';
            }
            for (var index = 0; index < my.actions.length; index++) {
                if (my.actions[index].dirty) {
                    return 'Unsaved data exist';
                }
            }
        });
    },
    methods: {
        addNewAction: function() {
            if (this.busy) {
                return;
            }
            var data = <?= json_encode(CertificateAction::create($certificate)) ?>;
            data.position = this.actions.length;
            this.actions.push(new DomainAction(data));
        },
        saveAction: function (action) {
            var my = this;
            if (my.busy) {
                return;
            }
            my.busy = true;
            $.ajax({
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action(['save_action', $certificate->getID()])) ?>,
                data: $.extend(
                    {
                        <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('acme-removeaction-' . $certificate->getID())) ?>,
                    },
                    action.getData()
                ),
            })
            .done(function(data, status, xhr) {
                ConcreteAjaxRequest.validateResponse(data, function(ok) {
                    if (ok) {
                        action.setData(data);
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
        askRemoveAction: function (action) {
            var my = this;
            if (my.busy) {
                return;
            }
            if (!window.confirm(<?= json_encode(t('Are you sure you want to remove this action?')) ?>)) {
                return;
            }
            if (action.isNew) {
                my.removeAction(action);
            }
            my.busy = true;
            $.ajax({
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action(['remove_action', $certificate->getID()])) ?>,
                data: {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('acme-removeaction-' . $certificate->getID())) ?>,
                    id: action.id,
                },
            })
            .done(function(data, status, xhr) {
                ConcreteAjaxRequest.validateResponse(data, function(ok) {
                    if (ok) {
                        my.removeAction(action);
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
        removeAction: function(action) {
            var index = this.actions.indexOf(action);
            if (index < 0) {
                return;
            }
            if (index === this.actions.length - 1) {
                this.actions.pop();
            } else {
                // Don't use splice, so that indexes are the same as positions
                this.actions[index] = undefined;
                this.$forceUpdate();
            }
        }
    },
});

});
</script>
