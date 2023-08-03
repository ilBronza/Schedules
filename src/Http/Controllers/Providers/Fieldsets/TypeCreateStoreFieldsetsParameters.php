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
                'fields' => [
                    // 'name' => ['text' => 'string|required|max:255'],
                    // 'validity' => ['text' => 'string|nullable|max:255'],
                    // 'measurement_unit_id' => [
                    //     'type' => 'select',
                    //     'multiple' => false,
                    //     'rules' => 'integer|nullable|exists:schedules__measurement_units,id',
                    //     'relation' => 'measurementUnit'
                    // ]
                ],
                'width' => ["1-3@l", '1-2@m']
            ],
            'applications' => [
                'fields' => [
                    'types' => [
                        'type' => 'json',
                        'fields' => [
                            'models' => [
                                'type' => 'select',
                                'multiple' => false,
                                'select2' => false,
                                'rules' => 'string|nullable|in:' . implode(',', $this->getModelsArray()),
                                'possibleValuesArray' => $this->getModelsArray(),
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
            ]
        ];
    }
}
