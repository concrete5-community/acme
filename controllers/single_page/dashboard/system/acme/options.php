<?php

namespace Concrete\Package\Acme\Controller\SinglePage\Dashboard\System\Acme;

use Acme\Service\UI;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Utility\Service\Validation\Numbers;

defined('C5_EXECUTE') or die('Access Denied.');

final class Options extends DashboardPageController
{
    public function view()
    {
        $config = $this->app->make('config');
        $this->set('renewDaysBeforeExpiration', $config->get('acme::renewal.daysBeforeExpiration'));
        $this->set('minimumKeySize', $config->get('acme::security.key_size.min'));
        $this->set('defaultKeySize', $config->get('acme::security.key_size.default'));
        $this->set('ui', $this->app->make(UI::class));
    }

    public function submit()
    {
        if (!$this->token->validate('acme-options')) {
            $this->error->add($this->token->getErrorMessage());
        }
        $post = $this->request->request;
        $config = $this->app->make('config');
        $valn = $this->app->make(Numbers::class);

        $renewDaysBeforeExpiration = $post->get('renewDaysBeforeExpiration');
        if ($valn->integer($renewDaysBeforeExpiration, 1)) {
            $renewDaysBeforeExpiration = (int) $renewDaysBeforeExpiration;
        } else {
            $this->error->add(t('Please specify a valid number of days for the allowed renewal of the certificates.'));
        }

        $minimumKeySize = $config->get('acme::security.minimumKeySize');
        $defaultKeySize = $post->get('defaultKeySize');
        if ($valn->integer($defaultKeySize, $minimumKeySize)) {
            $defaultKeySize = (int) $defaultKeySize;
        } else {
            $this->error->add(t('The minimum size of the private keys is %s bits', $minimumKeySize));
        }

        if ($this->error->has()) {
            return $this->view();
        }

        $config->save('acme::renewal.daysBeforeExpiration', $renewDaysBeforeExpiration);
        $config->save('acme::security.key_size.default', $defaultKeySize);

        $this->flash('success', t('The options have been saved.'));

        return $this->app->make(ResponseFactoryInterface::class)->redirect(
            $this->action(''),
            302
        );
    }
}
