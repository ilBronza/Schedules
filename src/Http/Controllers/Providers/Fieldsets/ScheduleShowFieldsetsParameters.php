<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\Fieldsets;

use IlBronza\Form\Helpers\FieldsetsProvider\FieldsetParametersFile;

class ScheduleShowFieldsetsParameters extends FieldsetParametersFile
{
    public function _getFieldsetsParameters() : array
    {
        return [
            'base' => [
                'translationPrefix' => 'schedules::fields',
                'fields' => [
                    'type_id' => [
                        'type' => 'text',
                        'readOnly' => true,
                        'rules' => 'string|nullable',
                    ],
                    'schedulable_type' => [
                        'type' => 'text',
                        'readOnly' => true,
                        'rules' => 'string|nullable',
                    ],
                    'schedulable_id' => [
                        'type' => 'text',
                        'readOnly' => true,
                        'rules' => 'string|nullable',
                    ],
                    'starting_value' => [
                        'type' => 'text',
                        'readOnly' => true,
                        'rules' => 'string|nullable',
                    ],
                    'deadline_value' => [
                        'type' => 'text',
                        'readOnly' => true,
                        'rules' => 'string|nullable',
                    ],
                    'expired_at' => [
                        'type' => 'text',
                        'readOnly' => true,
                        'rules' => 'string|nullable',
                    ],
                    'managed_at' => [
                        'type' => 'text',
                        'readOnly' => true,
                        'rules' => 'string|nullable',
                    ],
                    'status' => [
                        'type' => 'text',
                        'readOnly' => true,
                        'rules' => 'string|nullable',
                    ],
                ],
                'width' => ["1-3@l", '1-2@m'],
            ],
        ];
    }
}
