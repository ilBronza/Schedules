<?php

use App\Models\ProjectSpecific\User;
use IlBronza\Schedules\Http\Controllers\MeasurementUnits\MeasurementUnitCrudController;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\MeasurementUnitFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\TypeApplicableModelsElementsFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\TypeApplicableModelsFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\TypeFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\MeasurementUnitCreateStoreFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\MeasurementUnitEditUpdateFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeCreateStoreFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeEditUpdateFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\RelationshipsManagers\MeasurementUnitRelationManager;
use IlBronza\Schedules\Http\Controllers\Types\TypeApplicateClassnameController;
use IlBronza\Schedules\Http\Controllers\Types\TypeApplicateController;
use IlBronza\Schedules\Http\Controllers\Types\TypeCrudController;
use IlBronza\Schedules\Models\MeasurementUnit;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
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
        'scheduledNotifications' => [
            'class' => ScheduledNotification::class,
            'table' => 'schedules__scheduled_notifications',
            // 'fieldsGroupsFiles' => [
            //     'index' => TypeFieldsGroupParametersFile::class
            // ],
            // 'relationshipsManagerClasses' => [
            //     'show' => TypeRelationManager::class
            // ],
            // 'parametersFiles' => [
            //     'create' => TypeCreateStoreFieldsetsParameters::class
            // ],
            // 'controllers' => [
            //     'index' => TypeIndexController::class,
            //     'create' => TypeCreateStoreController::class,
            //     'store' => TypeCreateStoreController::class,
            //     'show' => TypeShowController::class,
            //     'edit' => TypeEditUpdateController::class,
            //     'update' => TypeEditUpdateController::class,
            //     'destroy' => TypeDestroyController::class,
            // ]
        ],
        'schedule' => [
            'class' => Schedule::class,
            'table' => 'schedules__schedules',
            // 'fieldsGroupsFiles' => [
            //     'index' => TypeFieldsGroupParametersFile::class
            // ],
            // 'relationshipsManagerClasses' => [
            //     'show' => TypeRelationManager::class
            // ],
            // 'parametersFiles' => [
            //     'create' => TypeCreateStoreFieldsetsParameters::class
            // ],
            // 'controllers' => [
            //     'index' => TypeIndexController::class,
            //     'create' => TypeCreateStoreController::class,
            //     'store' => TypeCreateStoreController::class,
            //     'show' => TypeShowController::class,
            //     'edit' => TypeEditUpdateController::class,
            //     'update' => TypeEditUpdateController::class,
            //     'destroy' => TypeDestroyController::class,
            // ]
        ],
        'type' => [
            'class' => Type::class,
            'table' => 'schedules__types',
            'fieldsGroupsFiles' => [
                'index' => TypeFieldsGroupParametersFile::class,
                'applicableModels' => TypeApplicableModelsFieldsGroupParametersFile::class,
                'applicableModelsElements' => TypeApplicableModelsElementsFieldsGroupParametersFile::class,
            ],
            // 'relationshipsManagerClasses' => [
            //     'show' => TypeRelationManager::class
            // ],
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
        ]
    ]
];