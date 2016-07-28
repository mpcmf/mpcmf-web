<?php

namespace mpcmf\apps\mpcmfWeb\libraries\acl;

use mpcmf\modules\authex\mappers\groupMapper;
use mpcmf\modules\authex\mappers\userMapper;
use mpcmf\modules\authex\models\groupModel;
use mpcmf\modules\authex\models\userModel;
use mpcmf\modules\moduleBase\actions\action;
use mpcmf\modules\moduleBase\exceptions\mapperException;
use mpcmf\modules\moduleBase\exceptions\modelException;
use mpcmf\modules\moduleBase\models\modelBase;
use mpcmf\modules\moduleBase\models\modelCursor;
use mpcmf\system\acl\aclManager;
use mpcmf\system\acl\aclManagerInterface;
use mpcmf\system\cache\cache;
use mpcmf\system\configuration\config;
use mpcmf\system\configuration\environment;
use mpcmf\system\configuration\exception\configurationException;
use mpcmf\system\helper\io\response;
use mpcmf\system\helper\system\profiler;
use mpcmf\system\pattern\singleton;
use mpcmf\system\storage\exception\storageException;
use mpcmf\system\token\tokenManager;

/**
 * Default system ACL manager
 *
 * @author Gregory Ostrovsky <greevex@gmail.com>
 */

/** @noinspection PropertyCanBeStaticInspection */
class defaultAclManager
    implements aclManagerInterface
{
    use singleton, response;

    const ACL__OK = 200;
    const ACL__FORBIDDEN = 403;
    const ACL__LIMIT_EXCEEDED = 429;

    private $config = [
        'token.sign' => 'ax:usr',
        'cookie.user' => 'ax:usr',
        'cookie.expire' => '+1 day'
    ];

    public function __construct()
    {
        if($this->config === null) {
            $this->config = config::getConfig(__CLASS__);
        }
        $this->config['cookie.user'] .= ':' . crc32(environment::getCurrentEnvironment());
    }

    public function checkActionAccessByToken(action $action, $tokenString, $checkLimits = true)
    {
        $tokenResponse = tokenManager::getInstance()->validateToken($tokenString, $checkLimits);

        if(!$tokenResponse['status']) {

            return $tokenResponse;
        }

        return $this->checkActionAccessByGroups($action, $tokenResponse['data']['g']);
    }

    /**
     * @param action $action
     * @param modelBase|userModel $currentUser
     *
     * @return array
     */
    public function checkActionAccess(action $action, modelBase $currentUser)
    {
        return $this->checkActionAccessByGroups($action, $currentUser->getGroupIds());
    }

    public function checkActionAccessByGroups(action $action, array $groups)
    {
        static $groupMapper;

        profiler::addStack('acl::check');

        $actionAclGroups = $action->getAcl();
        $actionAclGroups[] = $action->getAclGroupName();
        $actionAclGroups[] = aclManager::ACL__GROUP_ROOT;
        $actionAclGroups = array_unique($actionAclGroups, SORT_ASC);
        $actionGroupsCacheKey = 'acl/groups/' . md5(implode(',', $actionAclGroups));
        $actionGroups = cache::getCached($actionGroupsCacheKey);
        if(!$actionGroups) {
            if($groupMapper === null) {
                $groupMapper = groupMapper::getInstance();
            }
            $cursor = $groupMapper->getAllBy([
                groupMapper::FIELD__GROUP_ALIAS => [
                    '$in' => $actionAclGroups
                ]
            ]);
            $actionGroups = $this->expandGroupsByCursor($cursor);

            cache::setCached($actionGroupsCacheKey, $actionGroups, 3600);
        }

        $groups = array_unique($groups, SORT_ASC);
        $userGroupsCacheKey = 'acl/groups/' . md5(implode(',', $groups));
        $userGroups = cache::getCached($userGroupsCacheKey);
        if(!$userGroups) {
            if($groupMapper === null) {
                $groupMapper = groupMapper::getInstance();
            }
            $cursor = $groupMapper->getAllBy([
                groupMapper::FIELD__GROUP_ALIAS => [
                    '$in' => $groups
                ]
            ]);

            $userGroups = $this->expandGroupsByCursor($cursor);

            cache::setCached($userGroupsCacheKey, $userGroups, 3600);
        }

        $found = array_intersect($userGroups, $actionGroups);

        if (count($found) > 0) {

            return self::success($found, self::ACL__OK);
        } else {

            return self::error([
                'message' => 'Forbidden'
            ], self::ACL__FORBIDDEN);
        }
    }

    /**
     * Get all expanded group ids
     *
     * @param $cursor
     *
     * @return mixed
     * @throws \mpcmf\modules\moduleBase\exceptions\modelException
     */
    public function expandGroupsByCursor(modelCursor $cursor)
    {
        $groups = [];

        foreach($this->expandGroupsRecursive($cursor, $groups) as $group) {
            $groups[$group] = true;
        }

        return array_keys($groups);
    }

    /**
     * @param modelCursor|groupModel[] $cursor
     * @param                          $userGroups
     *
     * @return array
     * @throws modelException
     */
    protected function expandGroupsRecursive(modelCursor $cursor, &$userGroups)
    {
        /** @var groupModel $group */
        foreach($cursor as $group) {
            $groupId = $group->getIdValue();

            if(!empty($userGroups[$groupId])) {

                continue;
            }

            yield $groupId;

            if($group->getIsMeta()) {
                foreach($this->expandGroupsRecursive($group->getGroups(), $userGroups) as $innerGroup) {
                    yield $innerGroup;
                }
            }
        }
    }

    /**
     * @param $data
     *
     * @return string
     */
    public function generateSign($data)
    {
        if(array_key_exists('sign', $data)) {
            unset($data['sign']);
        }
        ksort($data);

        return substr(sha1(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), -8);
    }

    /**
     * @param array $groupsList
     *
     * @return mixed
     * @throws \InvalidArgumentException
     * @throws \MongoConnectionException
     * @throws \MongoCursorException
     * @throws configurationException
     * @throws storageException
     * @throws \Exception
     */
    public function createGroupsByList(array $groupsList)
    {
        foreach ($groupsList as $groupName) {
            groupMapper::getInstance()->updateBy([
                groupMapper::FIELD__GROUP_ALIAS => $groupName,
            ], [
                groupMapper::FIELD__GROUP_ALIAS => $groupName,
                groupMapper::FIELD__GROUP_NAME => $groupName
            ], [
                'upsert' => true
            ]);
        }
    }

    /**
     * @param $cookieData
     *
     * @return mixed
     * @throws \mpcmf\modules\moduleBase\exceptions\modelException
     * @throws mapperException
     */
    public function getCurrentUser($cookieData)
    {
        if(!$cookieData) {
            static $guestCacheKey = 'acl/user/guest';

            if(!($guestData = cache::getCached($guestCacheKey))) {
                $guestModel = userMapper::getInstance()->getGuestUser();
                /** @noinspection ReferenceMismatchInspection */
                $guestData = $guestModel->export();
                cache::setCached($guestCacheKey, $guestData, 3600);
            } else {
                $guestModel = userModel::fromArray($guestData);
            }

            return $guestModel;
        }

        $userId = $cookieData[userMapper::FIELD__USER_ID];
        $userCacheKey = "acl/user/{$userId}";
        if(!($userData = cache::getCached($userCacheKey))) {
            $userModel = userMapper::getInstance()->getById($userId);
            /** @noinspection ReferenceMismatchInspection */
            $userData = $userModel->export();
            cache::setCached($userCacheKey, $userData, 300);
        } else {
            $userModel = userModel::fromArray($userData);
        }

        return $userModel;
    }

    /**
     * @param modelBase|userModel $user
     *
     * @return array
     * @throws modelException
     */
    public function buildCookieDataByUser(modelBase $user)
    {
        return [
            'id' => $user->getIdValue(),
            'name' => $user->getFirstName(),
            'groups' => $user->getGroupIds(),
            'email' => $user->getEmail()
        ];

    }
}