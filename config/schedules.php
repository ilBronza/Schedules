<?php

use App\Models\ProjectSpecific\User;
use IlBronza\Schedules\Http\Controllers\MeasurementUnits\MeasurementUnitCrudController;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\MeasurementUnitFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\ScheduleRelatedFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\ScheduledNotificationRelatedFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\TypeApplicableModelsElementsFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\TypeApplicableModelsFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\TypeFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\TypeNotificationFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\TypeNotificationRelatedFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\MeasurementUnitCreateStoreFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\MeasurementUnitEditUpdateFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeCreateStoreFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeEditUpdateFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeNotificationEditUpdateFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\RelationshipsManagers\MeasurementUnitRelationManager;
use IlBronza\Schedules\Http\Controllers\Providers\RelationshipsManagers\TypeNotificationRelationshipManager;
use IlBronza\Schedules\Http\Controllers\Providers\RelationshipsManagers\TypeRelationshipManager;
use IlBronza\Schedules\Http\Controllers\ScheduledNotifications\ScheduledNotificationCrudController;
use IlBronza\Schedules\Http\Controllers\Schedules\ScheduleCrudController;
use IlBronza\Schedules\Http\Controllers\TypeNotifications\TypeNotificationCrudController;
use IlBronza\Schedules\Http\Controllers\Types\TypeApplicateClassnameController;
use IlBronza\Schedules\Http\Controllers\Types\TypeApplicateController;
use IlBronza\Schedules\Http\Controllers\Types\TypeCrudController;
use IlBronza\Schedules\Models\MeasurementUnit;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Models\TypeNotification;
use IlBronza\Vehicles\Models\Vehicle;


return [
    'routePrefix' => 'ibSchedules.',

    'applicableTo' => [
        User::class => 'users',
        Vehicle::class => 'vehicles'
    ],

    'models' => [
        // 'measurementUnit' => [
        //     'class' => MeasurementUnit::class,
        //     'table' => 'schedules__measurement_units',
        //     'fieldsGroupsFiles' => [
        //         'index' => MeasurementUnitFieldsGroupParametersFile::class
        //     ],
        //     'relationshipsManagerClasses' => [
        //         'show' => MeasurementUnitRelationManager::class
        //     ],
        //     'parametersFiles' => [
        //         'create' => MeasurementUnitCreateStoreFieldsetsParameters::class,
        //         'edit' => MeasurementUnitEditUpdateFieldsetsParameters::class
        //     ],
        //     'controllers' => [
        //         'index' => MeasurementUnitCrudController::class,
        //         'create' => MeasurementUnitCrudController::class,
        //         'store' => MeasurementUnitCrudController::class,
        //         'show' => MeasurementUnitCrudController::class,
        //         'edit' => MeasurementUnitCrudController::class,
        //         'update' => MeasurementUnitCrudController::class,
        //         'destroy' => MeasurementUnitCrudController::class,
        //     ]
        // ],
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
                'related' => ScheduleRelatedFieldsGroupParametersFile::class
            ],
            // 'relationshipsManagerClasses' => [
            //     'show' => TypeRelationManager::class
            // ],
            // 'parametersFiles' => [
            //     'create' => TypeCreateStoreFieldsetsParameters::class
            // ],
            'controllers' => [
                'index' => ScheduleCrudController::class,
                'create' => ScheduleCrudController::class,
                'store' => ScheduleCrudController::class,
                'show' => ScheduleCrudController::class,
                'edit' => ScheduleCrudController::class,
                'update' => ScheduleCrudController::class,
                'destroy' => ScheduleCrudController::class,
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
                'edit' => TypeEditUpdateFieldsetsParameters::class
            ],
            'controllers' => [
                'applicate' => TypeApplicateController::class,
                'applicateClassname' => TypeApplicateClassnameController::class,
                'index' => TypeCrudController::class,
                'create' => TypeCrudController::class,
                'store' => TypeCrudController::class,
                'show' => TypeCrudController::class,
                'edit' => TypeCrudController::class,
                'update' => TypeCrudController::class,
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