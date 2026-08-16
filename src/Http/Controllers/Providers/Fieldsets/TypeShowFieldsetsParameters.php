<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\Fieldsets;

use IlBronza\Form\Helpers\FieldsetsProvider\FieldsetParametersFile;

class TypeShowFieldsetsParameters extends FieldsetParametersFile
{
    public function _getFieldsetsParameters() : array
    {
        return [
            'base' => [
                'translationPrefix' => 'schedules::fields',
                'fields' => [
                    'name' => [
                        'type' => 'text',
                        'readOnly' => true,
                        'rules' => 'string|nullable|max:255',
                    ],
                    'validity' => [
                        'type' => 'text',
                        'readOnly' => true,
                        'rules' => 'string|nullable',
                    ],
                    'percentage_validity' => [
                        'type' => 'number',
                        'readOnly' => true,
                        'rules' => 'numeric|nullable',
                    ],
                    'allow_multiple' => [
                        'type' => 'boolean',
                        'readOnly' => true,
                        'rules' => 'bool|nullable',
                    ],
                    'measurement_unit_id' => [
                        'type' => 'text',
                        'readOnly' => true,
                        'rules' => 'string|nullable',
                    ],
                    'models' => [
                        'type' => 'json',
                        'readOnly' => true,
                        'fields' => [
                            'model' => ['text' => 'string|nullable'],
                            'source' => ['text' => 'string|nullable'],
                            'method' => ['text' => 'string|nullable'],
                        ],
                        'rules' => 'array|nullable',
                    ],
                    'roles' => [
                        'type' => 'json',
                        'readOnly' => true,
                        'fields' => [
                            'roles' => ['text' => 'string|nullable'],
                        ],
                        'rules' => 'array|nullable',
                    ],
                ],
                'width' => ["1-3@l", '1-2@m'],
            ],
        ];
    }
}
