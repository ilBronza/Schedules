<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\Fieldsets;

use IlBronza\Form\Helpers\FieldsetsProvider\FieldsetParametersFile;
use Spatie\Permission\Models\Role;

class TypeCreateStoreFieldsetsParameters extends FieldsetParametersFile
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
                'translationPrefix' => 'schedules::fields',
                'fields' => [
                    'name' => ['text' => 'string|required|max:255'],
                    'validity' => ['number' => 'numeric|nullable'],
                    'percentage_validity' => ['number' => 'numeric|nullable|min:0|max:100'],
                    'allow_multiple' => ['boolean' => 'bool|required'],
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
                'translationPrefix' => 'schedules::fields',
                'fields' => [
                    'models' => [
                        'type' => 'json',
                        'fields' => [
                            'model' => [
                                'type' => 'select',
                                'multiple' => false,
                                'select2' => false,
                                'rules' => 'string|nullable|in:' . implode(',', array_keys($this->getModelsArray())),
                                'possibleValuesArray' => $this->getModelsArray(),
                                'roles' => ['superadmin', 'administrator']
                            ],
                        ],
                        'rules' => 'array|nullable',
                    ],
                ],
                'width' => ["1-3@l", '1-2@m']
            ],
            'roles' => [
                'translationPrefix' => 'schedules::fields',
                'fields' => [
                    'roles' => [
                        'type' => 'json',
                        'fields' => [
                            'roles' => [
                                'type' => 'select',
                                'multiple' => false,
                                'select2' => false,
                                'rules' => 'string|nullable|in:' . implode(',', array_keys($this->getRolesArray())),
                                'possibleValuesArray' => $this->getRolesArray(),
                                'roles' => ['superadmin', 'administrator']
                            ],
                        ],
                        'rules' => 'array|nullable',
                    ],
                ],
                'width' => ["1-3@l", '1-2@m']
            ],
        ];
    }
}
