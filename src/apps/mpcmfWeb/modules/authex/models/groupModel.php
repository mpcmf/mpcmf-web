<?php

namespace mpcmf\modules\authex\models;

use mpcmf\system\cache\cache;
use mpcmf\modules\authex\mappers\userMapper;
use mpcmf\modules\moduleBase\models\modelBase;
use mpcmf\system\acl\aclManager;
use mpcmf\system\pattern\singleton;

/**
 * Model class
 *
 * @method getGroups()
 * @method setGroups($groups)
 * @method getIsMeta()
 * @method setIsMeta(bool $isMeta)
 * @method getGroupIds() return groups ids without expanding meta groups
 * @method setGroupIds(array $ids)
 * @author Gregory Ostrovsky <greevex@gmail.com>
 *
 * @date: 2/27/15 1:41 PM
 */
class groupModel
    extends modelBase
{

    use singleton;

    public function getUsersByGroup(groupModel $groupModel = null)
    {
        if ($groupModel === null) {
            $groupModel = $this;
        }

        $userMapper = userMapper::getInstance();

        return $userMapper->getAllBy([
            userMapper::FIELD__GROUPS => $groupModel->getIdValue(),
        ]);
    }
}