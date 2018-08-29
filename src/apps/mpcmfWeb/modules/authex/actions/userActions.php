<?php

namespace mpcmf\modules\authex\actions;

use mpcmf\modules\moduleBase\actions\action;
use mpcmf\modules\moduleBase\actions\actionsBase;
use mpcmf\modules\moduleBase\exceptions\actionException;
use mpcmf\system\acl\aclManager;
use mpcmf\system\pattern\singleton;

/**
 * Routes map class
 *
 * @author Gregory Ostrovsky <greevex@gmail.com>
 *
 * @date: 2/27/15 1:41 PM
 */
class userActions
    extends actionsBase
{
    use singleton;

    /**
     * Set options inside this method
     *
     * @return mixed
     */
    public function setOptions()
    {
        // TODO: Implement setOptions() method.
    }

    /**
     * Bind some custom actions
     *
     * @return mixed
     *
     * @throws actionException
     */
    public function bind()
    {
        $this->registerAction('register', new action([
            'name' => 'Регистрация пользователя',
            'method' => '_register',
            'path' => '/register(/:invite)',
            'http' => [
                'GET',
                'POST'
            ],
            'required' => [
            ],
            'template' => 'authex/register.tpl',
            'useBase' => false,
            'relative' => false,
            'type' => action::TYPE__DEFAULT,
            'acl' => [
                aclManager::ACL__GROUP_GUEST
            ],
        ], $this));
        $this->registerAction('login', new action([
            'name' => 'Авторизация',
            'method' => '_login',
            'path' => '/login',
            'http' => [
                'GET',
                'POST'
            ],
            'required' => [
            ],
            'template' => 'authex/login.tpl',
            'useBase' => false,
            'relative' => false,
            'type' => action::TYPE__DEFAULT,
            'acl' => [
                aclManager::ACL__GROUP_GUEST
            ],
        ], $this));
        $this->registerAction('passwordRecovery', new action([
            'name' => 'Восстановление пароля',
            'method' => '_passwordRecovery',
            'path' => '/passwordRecovery',
            'http' => [
                'GET',
                'POST'
            ],
            'required' => [
            ],
            'template' => 'authex/passRecovery.tpl',
            'useBase' => false,
            'relative' => false,
            'type' => action::TYPE__DEFAULT,
            'acl' => [
                aclManager::ACL__GROUP_GUEST
            ],
        ], $this));
        $this->registerAction('logout', new action([
            'name' => 'Выход из системы',
            'method' => '_logout',
            'path' => '/logout',
            'http' => [
                'GET',
                'POST'
            ],
            'required' => [
            ],
            'template' => 'authex/logout.tpl',
            'useBase' => false,
            'relative' => false,
            'type' => action::TYPE__DEFAULT,
            'acl' => [
                aclManager::ACL__GROUP_USER
            ],
        ], $this));
        $this->registerAction('forbidden', new action([
            'name' => 'Доступ запрещен',
            'method' => '_forbidden',
            'path' => '/forbidden',
            'http' => [
                'GET'
            ],
            'required' => [
            ],
            'template' => 'authex/forbidden.tpl',
            'useBase' => false,
            'relative' => false,
            'type' => action::TYPE__DEFAULT,
            'acl' => [
                aclManager::ACL__GROUP_GUEST,
                aclManager::ACL__GROUP_USER,
                aclManager::ACL__GROUP_ADMIN
            ],
        ], $this));
        $this->registerAction('api.forbidden', new action([
            'name' => 'Доступ запрещен',
            'method' => '_forbidden',
            'path' => '/forbidden',
            'http' => [
                'GET'
            ],
            'required' => [
            ],
            'template' => 'authex/forbidden.tpl',
            'useBase' => false,
            'relative' => false,
            'type' => action::TYPE__DEFAULT,
            'acl' => [
                aclManager::ACL__GROUP_GUEST,
                aclManager::ACL__GROUP_USER,
                aclManager::ACL__GROUP_ADMIN
            ],
        ], $this));
        $this->registerAction('profile', new action([
            'name' => 'Профиль пользователя',
            'method' => '_profile',
            'path' => '/profile',
            'http' => [
                'GET',
                'POST'
            ],
            'required' => [
            ],
            'template' => 'authex/profile.tpl',
            'useBase' => true,
            'relative' => false,
            'type' => action::TYPE__DEFAULT,
            'acl' => [
                aclManager::ACL__GROUP_USER
            ]
        ], $this));

        $this->registerAction('changePassword', new action([
            'name' => 'Изменить пароль',
            'method' => '_changePassword',
            'path' => '/changePassword',
            'http' => [
                'POST'
            ],
            'required' => [
            ],
            'template' => 'json.pretty.tpl',
            'useBase' => false,
            'relative' => false,
            'type' => action::TYPE__DEFAULT,
            'acl' => [
                aclManager::ACL__GROUP_USER
            ]
        ], $this));

        $this->registerAction('api.invite.generate', new action([
            'name' => 'Генерация инвайта',
            'method' => '_invite_generate',
            'path' => '/invite.generate',
            'http' => [
                'POST',
            ],
            'required' => [
            ],
            'template' => 'json.pretty.tpl',
            'useBase' => false,
            'relative' => false,
            'type' => action::TYPE__API_FREE_ACCESS,
            'acl' => [
                aclManager::ACL__GROUP_ADMIN
            ]
        ], $this));

        $this->registerAction('api.oauth.token', new action([
            'name' => 'oAuth',
            'method' => '_oauthToken',
            'path' => '/oauth/token',
            'http' => [
                'POST',
            ],
            'required' => [
            ],
            'template' => 'json.tpl',
            'useBase' => false,
            'relative' => false,
            'type' => action::TYPE__API_FREE_ACCESS,
            'acl' => [
                aclManager::ACL__GROUP_GUEST,
                aclManager::ACL__GROUP_USER,
            ]
        ], $this));
    }
}