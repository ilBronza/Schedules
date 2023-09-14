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
                'schedules' => 'relations.hasMany',
                'mySelfScheduleTypes' => [
                    'type' => 'iterators.list',
                    'function' => 'getApplicatedScheduleTypesList'
                ]
            ]
        ];
	}
}