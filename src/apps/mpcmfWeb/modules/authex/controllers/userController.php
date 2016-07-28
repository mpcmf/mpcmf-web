<?php

namespace mpcmf\modules\authex\controllers;

use mpcmf\system\cache\cache;
use mpcmf\modules\authex\entities\invite;
use mpcmf\modules\authex\mappers\inviteMapper;
use mpcmf\modules\authex\mappers\tokenMapper;
use mpcmf\modules\authex\mappers\userMapper;
use mpcmf\modules\authex\models\inviteModel;
use mpcmf\modules\authex\models\tokenModel;
use mpcmf\modules\authex\models\userModel;
use mpcmf\modules\moduleBase\controllers\controllerBase;
use mpcmf\modules\moduleBase\exceptions\controllerException;
use mpcmf\modules\moduleBase\exceptions\mapperException;
use mpcmf\modules\moduleBase\exceptions\modelException;
use mpcmf\modules\moduleBase\exceptions\moduleException;
use mpcmf\system\acl\aclManager;
use mpcmf\system\application\exception\webApplicationException;
use mpcmf\system\helper\communication\mail;
use mpcmf\system\helper\io\codes;
use mpcmf\system\helper\module\exception\modulePartsHelperException;
use mpcmf\system\pattern\singleton;

/**
 * Base controller class
 *
 * @author Gregory Ostrovsky <greevex@gmail.com>
 *
 * @date: 2/27/15 1:41 PM
 */
class userController
    extends controllerBase
{

    use singleton;

    /**
     * @param null|string $invite
     *
     * @return array
     * @throws mapperException
     * @throws webApplicationException
     * @throws moduleException
     * @throws modulePartsHelperException
     * @throws modelException
     */
    public function _register($invite = null)
    {
        $loginFields = [
            'email' => userMapper::FIELD__EMAIL,
            'login' => userMapper::FIELD__LOGIN,
            'password' => userMapper::FIELD__PASSWORD,
            'first_name' => userMapper::FIELD__FIRST_NAME,
            'last_name' => userMapper::FIELD__LAST_NAME,
        ];

        $readonlyFields = [];

        $hiddenFields = [
            'login' => true,
        ];

        $errors = '';

        $slim = $this->getSlim();
        $request = $slim->request();
        $inviteMapper = inviteMapper::getInstance();

        if($request->isPost()) {
            /** @var userMapper $userMapper */
            $userMapper = $this->getMapper();

            $item = $request->post('item');
            $item['login'] = $item['email'];

            $input = $userMapper->convertDataFromForm($item);

            if (!$this->checkInputByValidator($input, $errorFields)) {
                return self::error([
                    'loginFields' => $loginFields,
                    'item' => $item,
                    'errorFields' => $errorFields
                ], codes::RESPONSE_CODE_FORM_FIELDS_ERROR);
            }

            try {
                /** @var userModel $model */
                $model = $this->createModelByInput($input);
            } catch(modelException $modelException) {

                return self::error([
                    'loginFields' => $loginFields,
                    'item' => $item,
                    'errors' => $modelException->getMessage()
                ], $modelException->getCode(), codes::RESPONSE_CODE_FAIL);
            }

            try {
                $model->setRegDate(time());
                $groupsIds = [];
                if ($invite !== null) {
                    /** @var inviteModel $inviteModel */
                    $inviteModel = $inviteMapper->getBy([
                        inviteMapper::FIELD__INVITE => $invite,
                    ]);

                    $referrerId = $inviteModel->getReferer();
                    $model->setReferer($referrerId);

                    $groupsIds = $inviteModel->getGroupIds();

                    $inviteModel->setUsed(true);
                    $inviteMapper->save($inviteModel);
                }
                $model->setGroupIds(array_merge($groupsIds, [aclManager::ACL__GROUP_USER]));
                $userMapper->save($model);
            } catch (mapperException $mapperException) {

                return self::error($mapperException->getMessage(), $mapperException->getCode(), codes::RESPONSE_CODE_FAIL);
            }

            $slim->redirectTo($this->getRouteNameForAction('login'));
        }

        if ($invite !== null) {
            try {
                /** @var inviteModel $inviteModel */
                $inviteModel = $inviteMapper->getBy([
                    inviteMapper::FIELD__INVITE => $invite,
                ]);
                $readonlyFields['email'] = true;
            } catch (mapperException $mapperException) {
                if (strpos($mapperException->getMessage(), 'not found') !== false) {
                    $errors = 'not found';
                }
            }
        }

        $response = [
            'loginFields' => $loginFields,
            'hiddenFields' => $hiddenFields,
            'readonlyFields' => $readonlyFields,
            'errors' => $errors,
        ];

        if (isset($inviteModel)) {
            $email = $inviteModel->getEmail();
            $response['item']['email'] = $email;
            $response['item']['login'] = $email;
        }

        return self::nothing($response);
    }

    public function _login()
    {
        $loginFields = [
            'login' => userMapper::FIELD__LOGIN,
            'password' => userMapper::FIELD__PASSWORD,
        ];

        $slim = $this->getSlim();
        $request = $slim->request();

        if ($request->isPost()) {
            $item = $request->post('item');

            $input = $this->getMapper()->convertDataFromForm($item);

            if (!$this->checkInputByValidator($input, $errors, true)) {

                return self::error([
                    'loginFields' => $loginFields,
                    'item' => $item,
                    'errorFields' => $errors
                ], codes::RESPONSE_CODE_FORM_FIELDS_ERROR);
            }

            try {
                /** @var userModel $foundUser */
                $foundUser = $this->getMapper()->getBy([
                    $loginFields['login'] => $input[$loginFields['login']],
                    $loginFields['password'] => $input[$loginFields['password']],
                ]);
            } catch(mapperException $mapperException) {

                return self::error([
                    'loginFields' => $loginFields,
                    'item' => $item,
                    'errors' => $mapperException->getMessage()
                ], $mapperException->getCode(), codes::RESPONSE_CODE_FAIL);
            }

            try {
                $foundUser->setLastVisit(time());
                $this->getMapper()->save($foundUser);
                aclManager::getInstance()->saveUserCookie($foundUser);
            } catch(mapperException $mapperException) {

                return self::error([
                    'loginFields' => $loginFields,
                    'item' => $item,
                    'errors' => $mapperException->getMessage()
                ], $mapperException->getCode(), codes::RESPONSE_CODE_FAIL);
            }

            if($redirectUrl = $request->get('redirectUrl')) {
                $slim->redirect(base64_decode($redirectUrl));
            } else {
                $slim->redirectTo($this->getRouteNameForAction('profile'));
            }
        }

        return self::nothing([
            'loginFields' => $loginFields
        ]);
    }

    public function _profile()
    {
        $inviteMapper = inviteMapper::getInstance();
        $user = aclManager::getInstance()->getCurrentUser();
        $slim = $this->getSlim();
        $tokenMapper = tokenMapper::getInstance();

        if ($slim->request()->isPost()) {
            // TODO :: extract it to another action (create invite)
            $input = $this->getSlim()->request()->post('item', []);

            if (!isset($input['email'])) {
                $slim->response()->write(json_encode([
                    'message' => 'email field is empty'
                ]));
                $slim->stop();
            }

            $response = $inviteMapper->createInvite($input, $user);

            if (!$response['status']) {
                $slim->response()->setStatus(409);
                $slim->response()->write(json_encode([
                    'message' => 'Invite already sended!'
                ]));
                $slim->stop();
            }

            try {
                $invites = $inviteMapper->getInvitesByUserModel($user)->export();
            } catch (mapperException $mapperException) {
                $invites = [];
            }

            $response['invites'] = $invites;

            $slim->response()->write(json_encode($response));
            $slim->stop();
        }

        try {
            /** @var tokenModel $tokenModel */
            $tokenModel = $tokenMapper->getBy([
                tokenMapper::FIELD__USER => $user->getUserId()
            ]);
            $token = $tokenModel->getToken();
        } catch (mapperException $mapperException) {
            $token = '';
        }

        try {
            $invites = $inviteMapper->getInvitesByUserModel($user)->export();
        } catch (mapperException $mapperException) {
            $invites = [];
        }

        return self::success([
            'user' => $user,
            'inviteEntity' => invite::getInstance(),
            'invites' => $invites,
            'token' => $token
        ]);
    }

    public function _changePassword()
    {
        /** @var userModel $user */
        $user = aclManager::getInstance()->getCurrentUser();
        $userMapper = $this->getMapper();
        $slim = $this->getSlim();
        $passData = $slim->request()->post('password');

        $oldPassHash = $userMapper->convert(userMapper::FIELD__PASSWORD, $passData['old']);

        $errorFields = [];
        foreach ($passData as $fieldName => &$fieldData) {
            $fieldData = trim($fieldData);
            if ($fieldData === '') {
                $errorFields[] = $fieldName;
            }
        }
        unset($fieldData);

        if (count($errorFields) !== 0) {

            return [
                'data' => self::error([
                    'errorFields' => $errorFields,
                    'message' => 'Fields can\'t to be empty'
                ])
            ];
        }

        if ($user->getPassword() !== $oldPassHash) {

            return [
                'data' => self::error([
                    'errorFields' => [
                        'old'
                    ],
                    'message' => "Old password is wrong! Old pass [{$passData['old']}] hash: {$oldPassHash} Current pass hash: {$user->getPassword()}"
                ])
            ];
        }

        if ($passData['new'] !== $passData['confirm']) {

            return [
                'data' => self::error([
                    'errorFields' => [
                        'new',
                        'confirm'
                    ],
                    'message' => 'New and confirmation passwords are not identical'
                ])
            ];
        }

        $newPassHash = $userMapper->convert(userMapper::FIELD__PASSWORD, $passData['new']);

        try {
            $user->setPassword($newPassHash);
            $userMapper->save($user);
            $this->getEntity()->getController()->getRouteNameForAction('');

            return [
                'data' => $this->_logout()
            ];
        } catch (mapperException $mapperException) {

            return [
                'data' => self::errorByException($mapperException)
            ];
        }
    }

    public function _invite_generate()
    {
        $currentUser = aclManager::getInstance()->getCurrentUser();
        $invite = md5($currentUser->getLogin() . microtime());

        /** @var array $invites */
        $invites = $currentUser->getRealInviteLinks();
        if (count($invites) >= userMapper::INVITE__LIMIT && !in_array(aclManager::ACL__GROUP_ROOT, $currentUser->getGroupIds(), true)) {
            throw new controllerException('You except invite limit');
        }

        $invites[] = "http://{$_SERVER['HTTP_HOST']}" . $this->getSlim()->urlFor('/authex/user/invite', ['invite' => $invite]);
        $currentUser->setRealInviteLinks($invites);

        $currentUser->getMapper()->save($currentUser);

        $result = [
            'invites' => $invites,
        ];

        $this->getSlim()->response()->header('Content-type', 'application/json');

        return self::success($result);
    }

    public function _logout()
    {
        aclManager::getInstance()->removeUserCookie();
        $redirectUrl = $this->getSlim()->request()->get('redirectUrl');
        if(!$redirectUrl) {
            $redirectUrl = base64_encode('/');
        }

        return self::success([
            'redirectUrl' => $redirectUrl
        ]);
    }

    public function _passwordRecovery()
    {
        $loginFields = [
            'email' => userMapper::FIELD__EMAIL,
        ];

        $mapper = $this->getMapper();
        $slim = $this->getSlim();

        if ($slim->request()->isPost()) {
            $item = $slim->request()->post('item');

            try {
                /** @var userModel $userModel */
                $userModel = $this->getMapper()->getBy([
                    $loginFields['email'] => $item[$loginFields['email']]
                ]);
                $cacheKey = "passwordRecovery/{$userModel->getIdValue()}";
                if (cache::getCached($cacheKey)) {
                    return self::error([
                        'message' => 'You exceed limit of pass recovery, come back tomorrow',
                        'loginFields' => $loginFields,
                    ]);
                }
                $newPassword = $this->generatePassword();
                $passwordHash = $mapper->convert(userMapper::FIELD__PASSWORD, $newPassword);
                $userModel->setPassword($passwordHash);

                $mapper->save($userModel);
                cache::setCached($cacheKey, true, 86400);
            } catch (mapperException $mapperException) {
                return self::error([
                    'message' => 'Something going wrong, try later',
                    'errors' => [
                        $mapperException->getMessage()
                    ],
                    'loginFields' => $loginFields
                ]);
            }

            $email = $userModel->getEmail();
            $body = <<<BODY
<p>Dear {$userModel->getFirstName()}!</p>

<p>Somebody start password recovery procedure</p>
<p>Now, your password was changed to</p>

<p><b>{$newPassword}</b></p>

<p>You can login with this pass and change it in your profile</p>

<p>Your SDS</p>
BODY;
;
            $this->sendMail([$email], 'Password recovery', $body);

            return self::success([]);
        }

        return self::nothing([
            'loginFields' => $loginFields,
        ]);
    }

    private function sendMail(array $recipients, $subject, $body)
    {
        $mail = mail::getInstance();
        $mail->Subject = $subject;
        $mail->Body = $body;

        foreach ($recipients as $recipient) {
            $mail->addAddress($recipient);
        }
        $mail->From = 'no-reply@sdstream.ru';

        $mail->send();
    }

    private function generatePassword($length = 8)
    {
        $alphabet = 'abcefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';

        return substr(str_shuffle($alphabet), 0, $length);
    }

    public function _forbidden()
    {
        $redirectUrl = $this->getSlim()->request()->get('redirectUrl');
        if(!$redirectUrl) {
            $redirectUrl = base64_encode('/');
        }

        return self::success([
            'redirectUrl' => $redirectUrl
        ]);
    }
}