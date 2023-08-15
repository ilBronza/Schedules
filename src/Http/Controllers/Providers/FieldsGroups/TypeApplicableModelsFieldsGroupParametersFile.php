<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups;

use IlBronza\Datatables\Providers\FieldsGroupParametersFile;

class TypeApplicableModelsFieldsGroupParametersFile extends FieldsGroupParametersFile
{
	static function getFieldsGroup() : array
	{
		return [
            'fields' => 
            [
                'mySelfPrimary' => 'primary',
                'schedulable_model_name' => 'flat',
                'mySelfElements' => [
                    'type' => 'function',
                    'function' => 'getSchedulableElementsCount',
                ],
            ]
        ];
	}
}