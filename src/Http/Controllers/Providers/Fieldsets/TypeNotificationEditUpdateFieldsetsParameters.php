<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\Fieldsets;

use IlBronza\Form\Helpers\FieldsetsProvider\FieldsetParametersFile;

class TypeNotificationEditUpdateFieldsetsParameters extends FieldsetParametersFile
{
    public function _getFieldsetsParameters() : array
    {
        return [
            'base' => [
                'fields' => [
                    'type_id' => [
                        'type' => 'text',
                        'disabled' => true,
                        'rules' => 'string|nullable|exists:' . config('schedules.models.type.table') . ',id',
                    ],
                    'measurementUnit' => [
                        'type' => 'select',
                        'multiple' => false,
                        'disabled' => true,
                        'rules' => 'string|nullable|exists:' . config('measurementunits.models.measurementUnit.table') . ',id',
                        'relation' => 'measurementUnit'
                    ],
                    'before' => ['number' => 'numeric|required'],
                    'urgency' => ['number' => 'numeric|nullable|min:0'],
                ],
                'width' => ["1-3@l", '1-2@m']
            ],
            'repetition' => [
                'fields' => [
                    'repeat_every' => [
                        'number' => 'numeric|nullable|gt:0|required_with:repeat_every_measurement_unit_id,last_repetition',
                    ],
                    'last_repetition' => [
                        'number' => 'numeric|nullable|min:0|lte:before',
                    ],
                    'repeat_every_measurement_unit_id' => [
                        'type' => 'select',
                        'multiple' => false,
                        'rules' => 'string|nullable|exists:' . config('measurementunits.models.measurementUnit.table') . ',id|required_with:repeat_every',
                        'relation' => 'repeatingMeasurementUnit'
                    ]
                ],
                'width' => ["1-3@l", '1-2@m']
            ]
        ];
    }
}
