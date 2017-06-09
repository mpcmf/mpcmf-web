<?php

namespace mpcmf\modules\authex\models;

use mpcmf\modules\authex\mappers\groupMapper;
use mpcmf\modules\authex\mappers\userMapper;
use mpcmf\modules\moduleBase\models\modelBase;
use mpcmf\system\acl\aclManager;
use mpcmf\system\application\applicationInstance;
use mpcmf\system\pattern\singleton;

/**
 * Model class
 *
 *
 * @method getUserId
 * @method setUserId
 * @method getFirstName
 * @method setFirstName
 * @method getLastName
 * @method setLastName
 * @method getPassword
 * @method setPassword(string $password)
 * @method getLogin
 * @method setLogin()
 * @method getEmail
 * @method setEmail
 * @method getGroups
 * @method setGroups
 * @method setGroupIds(array $ids)
 * @method array getGroupIds()
 * @method getRegDate
 * @method setRegDate(int $date)
 * @method getLastVisit
 * @method setLastVisit(int $date)
 * @method getReferer
 * @method setReferer(string $refererId)
 * @method array getInviteLinks()
 * @method setInviteLinks(array $array)
 *
 * @author Gregory Ostrovsky <greevex@gmail.com>
 * @package mpcmf\modules\authex\models
 *
 * @date: 2/27/15 1:41 PM
 */
class userModel
    extends modelBase
{
    use singleton;

    const SPF_GRAVATAR = 'http://www.gravatar.com/avatar/%32s?s=%d&d=mm';
    const SPF_INVITE = 'http://%s%s';

    private $fullName;
    private $isGuestValue;
    private $isRootValue;
    private $isAdminValue;
    private $gravatarHash;
    private $inviteLinks;

    public function isGuest()
    {
        if($this->isGuestValue === null) {
            $this->isGuestValue = $this->getFieldValue(userMapper::FIELD__LOGIN) === aclManager::ACL__GROUP_GUEST;
        }

        return $this->isGuestValue;
    }

    public function isRoot()
    {
        if($this->isRootValue === null) {
            $this->isRootValue = in_array(aclManager::ACL__GROUP_ROOT, $this->getGroupIds(), true);
        }

        return $this->isRootValue;
    }

    public function isAdmin()
    {
        if($this->isAdminValue === null) {
            $this->isAdminValue = in_array(aclManager::ACL__GROUP_ADMIN, $this->getGroupIds(), true);
        }

        return $this->isAdminValue;
    }

    public function getFullName()
    {
        if($this->fullName === null) {
            $this->fullName = trim("{$this->getFirstName()} {$this->getLastName()}");
        }

        return $this->fullName;
    }

    public function getAvatarLink($width = 150)
    {
        if($this->gravatarHash === null) {
            $this->gravatarHash = md5(strtolower($this->getEmail()));
        }

        return sprintf(self::SPF_GRAVATAR, $this->gravatarHash, $width);
    }

    public function getRealInviteLinks()
    {
        if($this->inviteLinks === null || array_diff(array_keys($this->inviteLinks), $this->getInviteLinks())) {
            $this->inviteLinks = [];
            $app = applicationInstance::getInstance()->getCurrentApplication();
            foreach($this->getInviteLinks() as $invite) {
                $invitePath = $app->getUrl('/authex/user/invite', ['invite' => $invite]);
                $this->inviteLinks[] = sprintf(self::SPF_INVITE, $_SERVER['HTTP_HOST'], $invitePath);
            }
        }

        return $this->inviteLinks;
    }

    /**
     * @param array $links
     *
     * @return mixed
     */
    public function setRealInviteLinks(array $links)
    {
        foreach($links as &$link) {
            $link = substr($link, -32);
        }

        return $this->setInviteLinks($links);
    }
}