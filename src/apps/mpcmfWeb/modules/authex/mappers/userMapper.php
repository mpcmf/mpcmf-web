<?php

namespace mpcmf\modules\authex\mappers;

use mpcmf\system\cache\cache;
use mpcmf\modules\authex\models\userModel;
use mpcmf\modules\moduleBase\exceptions\mapperException;
use mpcmf\modules\moduleBase\mappers\mapperBase;
use mpcmf\modules\moduleBase\models\modelBase;
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
class userMapper
    extends mapperBase
{

    use singleton;

    const FIELD__USER_ID = 'id';
    const FIELD__LOGIN = 'login';
    const FIELD__PASSWORD = 'password';
    const FIELD__FIRST_NAME = 'first_name';
    const FIELD__LAST_NAME = 'last_name';
    const FIELD__EMAIL = 'email';
    const FIELD__GROUPS = 'groups';
    const FIELD__REG_DATE = 'reg_date';
    const FIELD__LAST_VISIT = 'last_visit';
    const FIELD__LAST_ACTIVITY = 'last_activity';
    const FIELD__REFERER = 'referer';

    const INVITE__LIMIT = 10;

    protected $defaultUsers = [
        [
            self::FIELD__LOGIN => aclManager::ACL__GROUP_GUEST,
            self::FIELD__PASSWORD => aclManager::ACL__GROUP_GUEST,
            self::FIELD__FIRST_NAME => 'Гость',
            self::FIELD__LAST_NAME => 'Гостевич',
            self::FIELD__EMAIL => 'guest@sdstream.ru',
            self::FIELD__GROUPS => [
                aclManager::ACL__GROUP_GUEST
            ],
            self::FIELD__REG_DATE => 0,
            self::FIELD__LAST_VISIT => 0,
            self::FIELD__LAST_ACTIVITY => 0,
            self::FIELD__REFERER => '',
        ]
    ];

    /**
     * @return array
     */
    public function getDefaultUsers()
    {
        return $this->defaultUsers;
    }

    /**
     * @return userModel
     *
     * @throws mapperException
     */
    public function getGuestUser()
    {
        try {
            $guestUser = $this->getBy([
                self::FIELD__LOGIN => aclManager::ACL__GROUP_GUEST
            ]);
        } catch(mapperException $mapperException) {
            if(strpos($mapperException->getMessage(), 'Item not found in storage') !== false) {
                $this->initialize(true);
            }
            $guestUser = $this->getBy([
                self::FIELD__LOGIN => aclManager::ACL__GROUP_GUEST
            ]);
        }

        return $guestUser;
    }

    protected function initialize($force = false)
    {
        $initCacheKey = 'auth/users/init/' . environment::getCurrentEnvironment();
        if($force || !cache::getCached($initCacheKey)) {
            foreach ($this->getDefaultUsers() as $defaultUser) {
                $defaultUser = $this->convertDataFromForm($defaultUser);
                if (!$this->_getBy([self::FIELD__LOGIN => $defaultUser[self::FIELD__LOGIN]])) {
                    $this->_create($defaultUser);
                }
            }
            cache::setCached($initCacheKey, true);
        }
    }

    /**
     * Entity map
     *
     * @return array[]
     */
    public function getMap()
    {
        return [
            self::FIELD__USER_ID => [
                'getter' => 'getUserId',
                'setter' => 'setUserId',
                'role' => [
                    self::ROLE__PRIMARY_KEY => true,
                    self::ROLE__GENERATE_KEY => true
                ],
                'type' => 'string',
                'formType' => 'text',
                'name' => 'ID пользователя',
                'description' => 'Уникальный идентификатор пользователя',
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
                            'pattern' => '/^[0-9a-f]{32}$/i'
                        ]
                    ]
                ],
                'options' => [
                    'required' => false,
                    'unique' => true
                ]
            ],
            self::FIELD__LOGIN => [
                'getter' => 'getLogin',
                'setter' => 'setLogin',
                'role' => [
                    self::ROLE__TITLE => true
                ],
                'type' => 'string',
                'formType' => 'text',
                'name' => 'Логин',
                'description' => 'Логин пользователя для входа в систему',
                'validator' => [
                    [
                        'type' => 'type.check',
                        'data' => [
                            'type' => 'string'
                        ]
                    ]
                ],
                'options' => [
                    'required' => true,
                    'unique' => true
                ]
            ],
            self::FIELD__PASSWORD => [
                'getter' => 'getPassword',
                'setter' => 'setPassword',
                'type' => 'string',
                'formType' => 'password',
                'name' => 'Пароль',
                'description' => 'Пароль пользователя для входа',
                'validator' => [
                    [
                        'type' => 'type.check',
                        'data' => [
                            'type' => 'string'
                        ]
                    ]
                ],
                'options' => [
                    'required' => true,
                    'unique' => false
                ]
            ],
            self::FIELD__FIRST_NAME => [
                'getter' => 'getFirstName',
                'setter' => 'setFirstName',
                'type' => 'string',
                'formType' => 'text',
                'name' => 'Имя',
                'description' => 'Имя пользователя',
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
                            'pattern' => '/^[0-9a-zA-Zа-яА-ЯёЁ]+$/ui'
                        ]
                    ]
                ],
                'options' => [
                    'required' => true,
                    'unique' => false
                ]
            ],
            self::FIELD__LAST_NAME => [
                'getter' => 'getLastName',
                'setter' => 'setLastName',
                'type' => 'string',
                'formType' => 'text',
                'name' => 'Фамилия',
                'description' => 'Фамилия пользователя',
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
                            'pattern' => '/^[0-9a-zA-Zа-яА-ЯёЁ\s]+$/ui'
                        ]
                    ]
                ],
                'options' => [
                    'required' => true,
                    'unique' => false
                ]
            ],
            self::FIELD__EMAIL => [
                'getter' => 'getEmail',
                'setter' => 'setEmail',
                'type' => 'string',
                'formType' => 'text',
                'name' => 'Email',
                'description' => 'Email пользователя',
                'validator' => [
                    [
                        'type' => 'type.check',
                        'data' => [
                            'type' => 'string'
                        ]
                    ],
                    [
                        'type' => 'email.byFilter',
                        'data' => []
                    ],
                    [
                        'type' => 'email.checkDomain',
                        'data' => []
                    ]
                ],
                'options' => [
                    'required' => true,
                    'unique' => true
                ]
            ],
            self::FIELD__GROUPS => [
                'getter' => 'getGroupIds',
                'setter' => 'setGroupIds',
                'type' => 'string[]',
                'formType' => 'searcheblemultiselect',
                'name' => 'Группы пользователя',
                'description' => 'Список групп доступа, в которых состоит пользователь',
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
            self::FIELD__REG_DATE => [
                'getter' => 'getRegDate',
                'setter' => 'setRegDate',
                'type' => 'int',
                'formType' => 'datetimepicker',
                'name' => 'Дата регистрации',
                'description' => 'Дата регистрации пользователя',
                'validator' => [
                    [
                        'type' => 'type.check',
                        'data' => [
                            'type' => 'numeric'
                        ]
                    ]
                ],
                'options' => [
                    'required' => false,
                    'unique' => false
                ]
            ],
            self::FIELD__LAST_VISIT => [
                'getter' => 'getLastVisit',
                'setter' => 'setLastVisit',
                'type' => 'int',
                'formType' => 'datetimepicker',
                'name' => 'Дата авторизации',
                'description' => 'Дата последней авторизации',
                'validator' => [
                    [
                        'type' => 'type.check',
                        'data' => [
                            'type' => 'numeric'
                        ]
                    ]
                ],
                'options' => [
                    'required' => false,
                    'unique' => false
                ]
            ],
            self::FIELD__LAST_ACTIVITY => [
                'getter' => 'getLastActivity',
                'setter' => 'setLastActivity',
                'type' => 'int',
                'formType' => 'datetimepicker',
                'name' => 'Последняя активность',
                'description' => 'Дата последней активности пользователя на сайте',
                'validator' => [
                    [
                        'type' => 'type.check',
                        'data' => [
                            'type' => 'numeric'
                        ]
                    ]
                ],
                'options' => [
                    'required' => false,
                    'unique' => false
                ]
            ],
            self::FIELD__REFERER => [
                'getter' => 'getReferer',
                'setter' => 'setReferer',
                'type' => 'string',
                'formType' => 'text',
                'name' => 'Реферер',
                'description' => 'Пригласивший',
                'validator' => [
                    [
                        'type' => 'type.check',
                        'data' => [
                            'type' => 'string'
                        ]
                    ]
                ],
                'options' => [
                    'required' => false,
                    'unique' => false
                ]
            ],
        ];
    }

    /**
     * @param                          $fieldName
     * @param modelBase|null           $model
     *
     * @return array
     * @throws \mpcmf\modules\moduleBase\exceptions\modelException
     * @throws \mpcmf\system\acl\exception\aclException
     * @throws mapperException
     */
    protected function relatedMapperCriteria($fieldName, modelBase $model = null)
    {
        $aclManager = aclManager::getInstance();

        /** @noinspection DegradedSwitchInspection */
        switch($fieldName) {
            case self::FIELD__GROUPS:

                /** @var userModel $currentUser */
                $currentUser = $aclManager->getCurrentUser();

                if($currentUser->isRoot()) {
                    $criteria = [];
                    break;
                }

                $groups = $aclManager->expandGroupsByCursor($currentUser->getGroups());
                $relationData = $this->getRelationData($fieldName);

                $criteria = [
                    $relationData['field'] => [
                        '$in' => $groups
                    ]
                ];
                break;
            default:
                $criteria = [];
                break;
        }

        return $criteria;
    }
}