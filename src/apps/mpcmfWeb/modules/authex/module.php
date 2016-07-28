<?php

namespace mpcmf\modules\authex;

use mpcmf\modules\authex\mappers\groupMapper;
use mpcmf\modules\moduleBase\moduleBase;
use mpcmf\system\pattern\singleton;

/**
 * auth application class
 *
 * @author Gregory Ostrovsky <greevex@gmail.com>
 * @date: 2/27/15 1:41 PM
 */
class module
    extends moduleBase
{
    use singleton;

    protected function bindAclGroups()
    {

    }

}