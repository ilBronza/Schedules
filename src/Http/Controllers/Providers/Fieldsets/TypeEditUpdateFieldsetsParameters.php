<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\Fieldsets;

use IlBronza\Form\Helpers\FieldsetsProvider\FieldsetParametersFile;
use Spatie\Permission\Models\Role;

class TypeEditUpdateFieldsetsParameters extends FieldsetParametersFile
{
    public function getRolesArray() : array
    {
        return Role::all()->pluck('name', 'id')->toArray();
    }

    public function getModelsArray() : array
    {
        return config('schedules.applicableTo');
    }

    public function _getFieldsetsParameters() : array
    {
        return [
            'base' => [
                'fields' => [
                    'name' => ['text' => 'string|required|max:255'],
                    'validity' => ['number' => 'numeric|nullable'],
                    'measurement_unit_id' => [
                        'type' => 'select',
                        'multiple' => false,
                        'rules' => 'string|nullable|exists:' . config('measurementUnits.models.measurementUnit.table') . ',id',
                        'relation' => 'measurementUnit'
                    ]
                ],
                'width' => ["1-3@l", '1-2@m']
            ],
            'applications' => [
                'fields' => [
                    'models' => [
                        'type' => 'json',
                        'fields' => [
                            'model' => [
                                'type' => 'select',
                                'multiple' => false,
                                'select2' => false,
                                'rules' => 'string|nullable|in:' . implode(',', $this->getModelsArray()),
                                'possibleValuesArray' => $this->getModelsArray(),
                                'roles' => ['superadmin', 'administrator']
                            ],
                            'source' => ['text' => 'string|required|max:255'],
                        ],
                        'rules' => 'array|nullable',
                    ],
                ],
                'width' => ["1-3@l", '1-2@m']
            ],
            'roles' => [
                'fields' => [
                    'roles' => [
                        'type' => 'json',
                        'fields' => [
                            'roles' => [
                                'type' => 'select',
                                'multiple' => false,
                                'select2' => false,
                                'rules' => 'string|nullable|in:' . implode(',', $this->getRolesArray()),
                                'possibleValuesArray' => $this->getRolesArray(),
                                'roles' => ['superadmin', 'administrator']
                            ],
                        ],
                        'rules' => 'array|nullable',
                    ],
                ],
                'width' => ["1-3@l", '1-2@m']
            ],
            'notifications' => [
                'fields' => [
                    'notifications' => [
                        'type' => 'json',
                        'fields' => [
                            'before' => ['number' => 'numeric|required|min:0'],
                            'repeat_every' => ['number' => 'numeric|required|min:0'],
                            'repeat_every_measurement_unit_id' => [
                                'type' => 'select',
                                'select2' => false,
                                'multiple' => false,
                                'rules' => 'string|nullable|exists:' . config('measurementUnits.models.measurementUnit.table') . ',id',
                                'relation' => 'measurementUnit'
                            ]
                        ],
                        'rules' => 'array|nullable',
                    ],
                ],
                'width' => ["1-1"]
            ]
        ];
    }
}
