<?php

namespace Acme\Controller;

use Acme\Editor\AccountEditor;
use Acme\Editor\ServerEditor;
use Acme\Entity\Account;
use Acme\Entity\Server;
use Concrete\Core\Application\Application;
use Concrete\Core\Error\ErrorList\ErrorList;
use Concrete\Core\Error\UserMessageException;
use Concrete\Core\Http\Request;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Utility\Service\Validation\Strings;
use Doctrine\ORM\EntityManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class FirstTimeSetup
{
    /**
     * @var \Concrete\Core\Application\Application
     */
    protected $app;

    /**
     * @param \Concrete\Core\Application\Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function createFirstServer()
    {
        $token = $this->app->make('token');
        if (!$token->validate('acme-firsttimesetup-firstserver')) {
            throw new UserMessageException($token->getErrorMessage());
        }
        if ($this->app->make(EntityManagerInterface::class)->getRepository(Server::class)->findOneBy([]) !== null) {
            throw new UserMessageException(t('The first ACME server is already defined'));
        }
        $post = $this->app->make(Request::class)->request;
        $category = $post->get('category');
        $handle = $post->get('handle');
        if ($this->app->make(Strings::class)->handle($category) && $this->app->make(Strings::class)->handle($handle)) {
            $data = $this->app->make('config')->get("acme::sample_servers.{$category}.{$handle}");
        } else {
            $data = null;
        }
        if (!is_array($data)) {
            throw new UserMessageException(t('Invalid ACME server specified'));
        }
        $errors = $this->app->make(ErrorList::class);
        $editor = $this->app->make(ServerEditor::class);
        $server = $editor->create($data, $errors);
        if ($server === null) {
            throw new UserMessageException($errors->toText());
        }

        return $this->app->make(ResponseFactoryInterface::class)->json([
            'name' => $server->getName(),
            'termsOfServiceUrl' => $server->getTermsOfServiceUrl(),
        ]);
    }

    /**
     * @throws \Concrete\Core\Error\UserMessageException
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function createFirstAccount()
    {
        $token = $this->app->make('token');
        if (!$token->validate('acme-firsttimesetup-firstaccount')) {
            throw new UserMessageException($token->getErrorMessage());
        }
        if ($this->app->make(EntityManagerInterface::class)->getRepository(Account::class)->findOneBy([]) !== null) {
            throw new UserMessageException(t('The first account is already defined'));
        }
        $servers = $this->app->make(EntityManagerInterface::class)->getRepository(Server::class)->findBy([], ['name' => 'ASC'], 2);
        switch (count($servers)) {
            case 0:
                throw new UserMessageException(t("There's no ACME server defined"));
            case 1:
                $server = $servers[0];
                break;
            default:
                throw new UserMessageException(t('More than one ACME server defined'));
        }
        $post = $this->app->make(Request::class)->request;
        $errors = $this->app->make(ErrorList::class);
        $editor = $this->app->make(AccountEditor::class);
        $data = [
            'name' => $post->get('name'),
            'email' => $post->get('email'),
            'acceptedTermsOfService' => $post->get('acceptedTermsOfService'),
        ];
        if (!$editor->create($server, $data, $errors)) {
            throw new UserMessageException($errors->toText());
        }

        return $this->app->make(ResponseFactoryInterface::class)->json(true);
    }
}
