<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups;

use IlBronza\Datatables\Providers\FieldsGroupParametersFile;

class TypeFieldsGroupParametersFile extends FieldsGroupParametersFile
{
	static function getFieldsGroup() : array
	{
		return [
            'fields' => 
            [
                'mySelfPrimary' => 'primary',
                'mySelfEdit' => 'links.edit',
                'mySelfSee' => 'links.see',
                'mySelfApplicate' => [
                    'type' => 'links.link',
                    'function' => 'getApplicateIndexUrl'
                ],

                'id' => 'flat',
                'name' => 'flat',
                'description' => 'flat',

                'mySelfDelete' => 'links.delete'
            ]
        ];
	}
}