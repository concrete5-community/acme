<?php

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Acme\Entity\Certificate $certificate
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $resolverManager
 * @var Concrete\Core\Localization\Service\Date $dateHelper
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Page\View\PageView $view
 */
if ($certificate->getOrders()->isEmpty()) {
    ?>
    <div class="alert alert-info">
        <?= t('The certificate renewal/reauthorization list is empty') ?>
    </div>
    <?php
} else {
    ?>
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th><?= t('Date') ?></th>
                <th><?= t('Status') ?></th>
                <th><?= t('Expiration') ?></th>
                <th><?= t('Authorizations') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($certificate->getOrders() as $order) {
                ?>
                <tr>
                    <td>
                        <?php
                        if ($order === $certificate->getOngoingOrder()) {
                            echo '<i class="fa fa-cog fa-spin fa-fw"></i> ';
                        }
                        echo h($dateHelper->formatPrettyDateTime($order->getCreatedOn()));
                        ?>
                    </td>
                    <td><?= h($order->getStatus()) ?></td>
                    <td><?= h($dateHelper->formatPrettyDateTime($order->getExpiration())) ?></td>
                    <td>
                        <ul>
                            <?php
                            foreach ($order->getAuthorizationChallenges() as $challenge) {
                                ?>
                                <li>
                                    <strong><?= h($challenge->getDomain()->getHostDisplayName()) ?></strong><br />
                                    <?= t('Authorization status: %s', $challenge->getAuthorizationStatus()) ?><br />
                                    <?= t('Challenge status: %s', $challenge->getChallengeStatus()) ?><br />
                                    <?php
                                    if ($challenge->getChallengeErrorMessage() !== '') {
                                        ?>
                                        <div class="alert alert-danger">
                                            <?= nl2br(h($challenge->getChallengeErrorMessage())) ?>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </li>
                                <?php
                            }
                            ?>
                        </ul>
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
    <?php
}

?>
<div class="ccm-dashboard-form-actions-wrapper">
    <div class="ccm-dashboard-form-actions">
        <a href="<?= h($resolverManager->resolve(['/dashboard/system/acme/certificates/edit', $certificate->getID()])) ?>" class="btn btn-default pull-left">
            <?= t('Back') ?>
        </a>
        <div class="pull-right">
            <?php
            if ($certificate->getOrders()->isEmpty()) {
                ?>
                <button disabled="disabled" class="btn btn-danger"><?= t('Clear history') ?></button>
                <?php
            } else {
                ?>
                <button id="acme-certificate-clearhistory" class="btn btn-danger"><?= t('Clear history') ?></button>
                <?php
            }
            ?>
        </div>
    </div>
</div>

<?php
if (!$certificate->getOrders()->isEmpty()) {
    ?>
    <form id="acme-certificate-clearhistory-do" method="POST" action="<?= h($view->action('clear_history', $certificate->getID())) ?>" class="hide">
        <?php $token->output('acme-certificate-clear_history-' . $certificate->getID()) ?>
    </form>
    <script>
    $(document).ready(function() {
        $('#acme-certificate-clearhistory').on('click', function(e) {
            e.preventDefault();
            if (window.confirm(<?= json_encode(t('Are you sure you want to clear the certificate renewal history?')) ?>)) {
                $('#acme-certificate-clearhistory-do').submit();
            }
        });
    });
    </script>
    <?php
}
?>