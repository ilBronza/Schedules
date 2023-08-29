<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups;

use IlBronza\Datatables\Providers\FieldsGroupParametersFile;

class TypeApplicableModelsElementsFieldsGroupParametersFile extends FieldsGroupParametersFile
{
	static function getFieldsGroup() : array
	{
		return [
            'fields' => 
            [
                'mySelfPrimary' => 'primary',
                'name' => 'flat',
                'mySelfScheduleTypes' => [
                    'type' => 'iterators.list',
                    'function' => 'getApplicatedScheduleTypes'
                ],
                'id' => 'flat'
            ]
        ];
	}
}