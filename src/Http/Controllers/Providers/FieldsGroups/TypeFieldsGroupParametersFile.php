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
                    'function' => 'getApplicateIndexUrl',
                    'faIcon' => 'clone'
                ],

                'name' => 'flat',
                'description' => 'flat',
                'typeNotifications' => 'relations.hasMany',

                'mySelfDelete' => 'links.delete'
            ]
        ];
	}
}