<?php

use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\ScheduleIndexFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\ScheduleRelatedFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\ScheduledNotificationRelatedFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\TypeApplicableModelsElementsFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\TypeApplicableModelsFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\TypeFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\TypeNotificationFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\TypeNotificationRelatedFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\ScheduleShowFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeCreateStoreFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeEditUpdateFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeNotificationEditUpdateFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeShowFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\RelationshipsManagers\TypeNotificationRelationshipManager;
use IlBronza\Schedules\Http\Controllers\Providers\RelationshipsManagers\TypeRelationshipManager;
use IlBronza\Schedules\Http\Controllers\ScheduledNotifications\ScheduledNotificationCrudController;
use IlBronza\Schedules\Http\Controllers\Schedules\ScheduleCrudController;
use IlBronza\Schedules\Http\Controllers\TypeNotifications\TypeNotificationCrudController;
use IlBronza\Schedules\Http\Controllers\Types\TypeApplicateClassnameController;
use IlBronza\Schedules\Http\Controllers\Types\TypeApplicateController;
use IlBronza\Schedules\Http\Controllers\Types\TypeCrudController;
use IlBronza\Schedules\Http\Controllers\Types\TypeEditUpdateController;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\ScheduledNotificationDispatch;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Models\TypeNotification;


return [
    'routePrefix' => 'ibSchedules.',
    'validateConfiguration' => true,

    'evaluator' => [
        'enabled' => true,
        'cron' => '* * * * *',
        'chunkSize' => 100,
        'overlapExpirationMinutes' => 10,
    ],

    'notifications' => [
        'roleModel' => null,
    ],

    'defaultRoles' => [
        'superadmin',
        'administrator',
        'schedules',
    ],

    'routeRoles' => [
    ],

    // Model class => stable URL alias (also used as select label).
    // Example: \App\Models\Vehicle::class => 'vehicles',
    // Route params must use the alias; never pass a PHP classname in the URL.
    'applicableTo' => [],

    'models' => [
        'scheduledNotificationDispatch' => [
            'class' => ScheduledNotificationDispatch::class,
            'table' => 'schedules__scheduled_notification_dispatches',
        ],
        'scheduledNotification' => [
            'class' => ScheduledNotification::class,
            'table' => 'schedules__scheduled_notifications',
            'fieldsGroupsFiles' => [
                'related' => ScheduledNotificationRelatedFieldsGroupParametersFile::class
            ],
            // 'relationshipsManagerClasses' => [
            //     'show' => TypeRelationManager::class
            // ],
            // 'parametersFiles' => [
            //     'create' => TypeCreateStoreFieldsetsParameters::class
            // ],
            'controllers' => [
                'index' => ScheduledNotificationCrudController::class,
                'create' => ScheduledNotificationCrudController::class,
                'store' => ScheduledNotificationCrudController::class,
                'show' => ScheduledNotificationCrudController::class,
                'edit' => ScheduledNotificationCrudController::class,
                'update' => ScheduledNotificationCrudController::class,
                'destroy' => ScheduledNotificationCrudController::class,
            ]
        ],
        'schedule' => [
            'class' => Schedule::class,
            'table' => 'schedules__schedules',
            'fieldsGroupsFiles' => [
				'index' => ScheduleIndexFieldsGroupParametersFile::class,
                'related' => ScheduleRelatedFieldsGroupParametersFile::class
            ],
            // 'relationshipsManagerClasses' => [
            //     'show' => TypeRelationManager::class
            // ],
            'parametersFiles' => [
                'show' => ScheduleShowFieldsetsParameters::class,
            ],
            'controllers' => [
                'index' => ScheduleCrudController::class,
                'show' => ScheduleCrudController::class,
                'manage' => ScheduleCrudController::class,
                'manageMany' => ScheduleCrudController::class,
            ]
        ],
        'type' => [
            'class' => Type::class,
            'table' => 'schedules__types',
            'fieldsGroupsFiles' => [
                'index' => TypeFieldsGroupParametersFile::class,
                'applicableModels' => TypeApplicableModelsFieldsGroupParametersFile::class,
                'applicableModelsElements' => TypeApplicableModelsElementsFieldsGroupParametersFile::class,
            ],
            'relationshipsManagerClasses' => [
                'show' => TypeRelationshipManager::class
            ],
            'parametersFiles' => [
                'create' => TypeCreateStoreFieldsetsParameters::class,
                'edit' => TypeEditUpdateFieldsetsParameters::class,
                'show' => TypeShowFieldsetsParameters::class,
            ],
            'controllers' => [
                'applicate' => TypeApplicateController::class,
                'applicateClassname' => TypeApplicateClassnameController::class,
                'index' => TypeCrudController::class,
                'create' => TypeCrudController::class,
                'store' => TypeCrudController::class,
                'show' => TypeCrudController::class,
                'edit' => TypeEditUpdateController::class,
                'update' => TypeEditUpdateController::class,
                'destroy' => TypeCrudController::class,
            ]
        ],
        'typeNotification' => [
            'class' => TypeNotification::class,
            'table' => 'schedules__type_notifications',
            'controllers' => [
                'index' => TypeNotificationCrudController::class,
                'create' => TypeNotificationCrudController::class,
                'store' => TypeNotificationCrudController::class,
                'show' => TypeNotificationCrudController::class,
                'edit' => TypeNotificationCrudController::class,
                'update' => TypeNotificationCrudController::class,
                'destroy' => TypeNotificationCrudController::class,
            ],
            'fieldsGroupsFiles' => [
                'index' => TypeNotificationFieldsGroupParametersFile::class,
                'related' => TypeNotificationRelatedFieldsGroupParametersFile::class,
            ],
            'relationshipsManagerClasses' => [
                'show' => TypeNotificationRelationshipManager::class
            ],
            'parametersFiles' => [
                'show' => TypeNotificationEditUpdateFieldsetsParameters::class,
                'create' => TypeNotificationEditUpdateFieldsetsParameters::class,
                'edit' => TypeNotificationEditUpdateFieldsetsParameters::class
            ],
        ]
    ]
];