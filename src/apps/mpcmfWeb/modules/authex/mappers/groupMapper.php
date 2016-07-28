<?php

namespace mpcmf\modules\authex\mappers;

use mpcmf\system\cache\cache;
use mpcmf\modules\moduleBase\mappers\mapperBase;
use mpcmf\system\acl\aclManager;
use mpcmf\system\configuration\environment;
use mpcmf\system\pattern\singleton;

/**
 * Model map class
 *
 * @author Gregory Ostrovsky <greevex@gmail.com>
 *
 * @date: 2/27/15 1:41 PM
 */
class groupMapper
    extends mapperBase
{
    use singleton;

    const FIELD__GROUP_ALIAS = 'alias';
    const FIELD__GROUP_NAME = 'name';
    const FIELD__IS_META = 'isMeta';
    const FIELD__GROUPS = 'groups';

    const ACL__GROUP_TWATCH = 'twatch';

    protected $defaultGroups = [
        aclManager::ACL__GROUP_ROOT => [
            self::FIELD__GROUP_ALIAS => aclManager::ACL__GROUP_ROOT,
            self::FIELD__GROUP_NAME => 'root',
        ],
        aclManager::ACL__GROUP_ADMIN => [
            self::FIELD__GROUP_ALIAS => aclManager::ACL__GROUP_ADMIN,
            self::FIELD__GROUP_NAME => 'Администратор',
        ],
        aclManager::ACL__GROUP_USER => [
            self::FIELD__GROUP_ALIAS => aclManager::ACL__GROUP_USER,
            self::FIELD__GROUP_NAME => 'Пользователь',
        ],
        aclManager::ACL__GROUP_GUEST => [
            self::FIELD__GROUP_ALIAS => aclManager::ACL__GROUP_GUEST,
            self::FIELD__GROUP_NAME => 'Гость',
        ],
        self::ACL__GROUP_TWATCH => [
            self::FIELD__GROUP_ALIAS => self::ACL__GROUP_TWATCH,
            self::FIELD__GROUP_NAME => 'threadWatch.*',
        ],
        aclManager::ACL__GROUP_CRUD_FULL => [
            self::FIELD__GROUP_ALIAS => aclManager::ACL__GROUP_CRUD_FULL,
            self::FIELD__GROUP_NAME => aclManager::ACL__GROUP_CRUD_FULL,
        ],
        aclManager::ACL__GROUP_CRUD_READ => [
            self::FIELD__GROUP_ALIAS => aclManager::ACL__GROUP_CRUD_READ,
            self::FIELD__GROUP_NAME => aclManager::ACL__GROUP_CRUD_READ,
        ],
        aclManager::ACL__GROUP_CRUD_WRITE => [
            self::FIELD__GROUP_ALIAS => aclManager::ACL__GROUP_CRUD_WRITE,
            self::FIELD__GROUP_NAME => aclManager::ACL__GROUP_CRUD_WRITE,
        ],
        aclManager::ACL__GROUP_API_FULL => [
            self::FIELD__GROUP_ALIAS => aclManager::ACL__GROUP_API_FULL,
            self::FIELD__GROUP_NAME => aclManager::ACL__GROUP_API_FULL,
        ],
        aclManager::ACL__GROUP_API_READ => [
            self::FIELD__GROUP_ALIAS => aclManager::ACL__GROUP_API_READ,
            self::FIELD__GROUP_NAME => aclManager::ACL__GROUP_API_READ,
        ],
        aclManager::ACL__GROUP_API_WRITE => [
            self::FIELD__GROUP_ALIAS => aclManager::ACL__GROUP_API_WRITE,
            self::FIELD__GROUP_NAME => aclManager::ACL__GROUP_API_WRITE,
        ],
    ];

    protected function initialize()
    {
        $initCacheKey = 'auth/groups/init/' . environment::getCurrentEnvironment();
        if(!cache::getCached($initCacheKey)) {
            foreach ($this->getDefaultGroups() as $defaultGroup) {
                if (!$this->_getBy([self::FIELD__GROUP_ALIAS => $defaultGroup[self::FIELD__GROUP_ALIAS]])) {
                    $this->_create($defaultGroup);
                }
            }
            cache::setCached($initCacheKey, true);
        }
    }

    /**
     * @return array
     */
    public function getDefaultGroups()
    {
        return $this->defaultGroups ;
    }

    /**
     * Entity map
     *
     * @return array[]
     */
    public function getMap()
    {
        return [
            self::FIELD__GROUP_ALIAS => [
                'getter' => 'getAlias',
                'setter' => 'setAlias',
                'role' => [
                    self::ROLE__PRIMARY_KEY => true,
                    self::ROLE__GENERATE_KEY => false,
                    self::ROLE__SEARCHABLE => true,
                ],
                'type' => 'string',
                'formType' => 'text',
                'name' => 'Алиас группы',
                'description' => 'Строковый алиас группы',
                'validator' => [
                    [
                        'type' => 'type.check',
                        'data' => [
                            'type' => 'string'
                        ]
                    ],
                    [
                        'type' => 'string.byRegex',
                        'data' => [
                            'pattern' => '/^[\pL]{2,}/ui'
                        ]
                    ]
                ],
                'options' => [
                    'required' => true,
                    'unique' => true
                ]
            ],
            self::FIELD__GROUP_NAME => [
                'getter' => 'getName',
                'setter' => 'setName',
                'role' => [
                    self::ROLE__TITLE => true,
                    self::ROLE__SEARCHABLE => true,
                ],
                'type' => 'string',
                'formType' => 'text',
                'name' => 'Имя группы доступа',
                'description' => 'Имя группы доступа',
                'validator' => [
                    [
                        'type' => 'type.check',
                        'data' => [
                            'type' => 'string'
                        ]
                    ],
                    [
                        'type' => 'string.byRegex',
                        'data' => [
                            'pattern' => '/^[\pL]{2,}/ui'
                        ]
                    ]
                ],
                'options' => [
                    'required' => true,
                    'unique' => true
                ]
            ],
            self::FIELD__IS_META => [
                'getter' => 'getIsMeta',
                'setter' => 'setIsMeta',
                'role' => [
                ],
                'type' => 'bool',
                'formType' => 'checkbox',
                'name' => 'Мета-группа',
                'description' => 'Мета-группа',
                'validator' => [
                ],
                'options' => [
                    'required' => false,
                    'unique' => false
                ]
            ],
            self::FIELD__GROUPS => [
                'getter' => 'getGroupsIds',
                'setter' => 'setGroupsIds',
                'role' => [
                ],
                'type' => 'string[]',
                'formType' => 'searcheblemultiselect',
                'name' => 'Группы',
                'description' => 'Вложенные группы',
                'validator' => [
                ],
                'relations' => [
                    'group' => [
                        'getter' => 'getGroups',
                        'setter' => 'setGroups',
                        'type' => self::RELATION__MANY_TO_MANY,
                        'mapper' => groupMapper::class
                    ]
                ],
                'options' => [
                    'required' => false,
                    'unique' => false
                ]
            ],
        ];
    }
}
