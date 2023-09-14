<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups;

use IlBronza\Datatables\Providers\FieldsGroupParametersFile;

class TypeNotificationRelatedFieldsGroupParametersFile extends FieldsGroupParametersFile
{
	static function getFieldsGroup() : array
	{
		return [
            'fields' => 
            [
                'mySelfPrimary' => 'primary',
                'mySelfEdit' => 'links.edit',
                'mySelfSee' => 'links.see',
                'before' => [
                    'type' => 'flat',
                    'order' => [
                        'priority' => 10,
                        'type' => 'DESC'
                    ]                    
                ],
                'measurementUnit.name' => 'flat',
                'urgency' => 'flat',
                'repeat_every' => 'flat',
                'repeatingMeasurementUnit.name' => 'flat',

                'mySelfDelete' => 'links.delete'
            ]
        ];
	}
}