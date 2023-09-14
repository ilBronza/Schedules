<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups;

use IlBronza\Datatables\Providers\FieldsGroupParametersFile;

class TypeNotificationFieldsGroupParametersFile extends FieldsGroupParametersFile
{
	static function getFieldsGroup() : array
	{
		return [
            'fields' => 
            [
                'mySelfPrimary' => 'primary',
                'mySelfEdit' => 'links.edit',
                'mySelfSee' => 'links.see',
                'type' => [
                    'type' => 'relations.belongsTo',
                    'order' => [
                        'priority' => 100
                    ]
                ],
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