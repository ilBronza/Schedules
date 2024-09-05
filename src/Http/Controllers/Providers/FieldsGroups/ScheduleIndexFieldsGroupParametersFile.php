<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups;

use IlBronza\Datatables\Providers\FieldsGroupParametersFile;

class ScheduleIndexFieldsGroupParametersFile extends FieldsGroupParametersFile
{
	static function getFieldsGroup() : array
	{
		return [
            'translationPrefix' => 'schedules::fields',
            'fields' => 
            [
                'mySelfPrimary' => 'primary',
                'mySelfEdit' => 'links.edit',
                'mySelfSee' => 'links.see',
                'created_at' => 'dates.date',

				'type' => 'relations.belongsTo',
				'schedulable' => 'relations.belongsTo',
                'typeNotifications' => 'relations.belongsToMany',
                'starting_value' => 'flat',
                'deadline_value' => 'flat',
//                'percentage_validity' => 'utilities.milestone',
                'current_value' => 'flat',

                'mySelfDelete' => 'links.delete'
            ]
        ];
	}
}