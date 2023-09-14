<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\Fieldsets;

use IlBronza\Form\Helpers\FieldsetsProvider\FieldsetParametersFile;
use Spatie\Permission\Models\Role;

class TypeNotificationEditUpdateFieldsetsParameters extends FieldsetParametersFile
{
    public function _getFieldsetsParameters() : array
    {
        return [
            'base' => [
                'fields' => [
                    'type_id' => [
                        'type' => 'select',
                        'multiple' => false,
                        'disabled' => true,
                        'rules' => 'string|nullable|exists:' . config('measurementUnits.models.type.table') . ',id',
                        'relation' => 'type'
                    ],
                    'measurementUnit' => [
                        'type' => 'select',
                        'multiple' => false,
                        'disabled' => true,
                        'rules' => 'string|nullable|exists:' . config('measurementUnits.models.type.table') . ',id',
                        'relation' => 'measurementUnit'
                    ],
                    'before' => ['number' => 'numeric|required'],
                    'urgency' => ['number' => 'numeric|nullable'],
                ],
                'width' => ["1-3@l", '1-2@m']
            ],
            'repetition' => [
                'fields' => [
                    'repeat_every' => ['number' => 'numeric|nullable'],
                    'repeat_every_measurement_unit_id' => [
                        'type' => 'select',
                        'multiple' => false,
                        'rules' => 'string|nullable|exists:' . config('measurementUnits.models.measurementUnit.table') . ',id',
                        'relation' => 'repeatingMeasurementUnit'
                    ]
                ],
                'width' => ["1-3@l", '1-2@m']
            ]
        ];
    }
}
