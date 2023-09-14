<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups;

use IlBronza\Datatables\Providers\FieldsGroupParametersFile;

class ScheduleRelatedFieldsGroupParametersFile extends FieldsGroupParametersFile
{
	static function getFieldsGroup() : array
	{
		return [
            'fields' => 
            [
                'mySelfPrimary' => 'primary',
                'mySelfEdit' => 'links.edit',
                'mySelfSee' => 'links.see',
                'created_at' => 'dates.date',

                'schedulable' => 'relations.belongsTo',
                'typeNotifications' => 'relations.belongsToMany',
                'starting_value' => 'flat',
                'current_value' => 'flat',
                'percentage_validity' => 'utilities.milestone',
                'deadline_value' => 'flat',

                'mySelfDelete' => 'links.delete'
            ]
        ];
	}
}