<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\RelationshipsManagers;

use IlBronza\CRUD\Providers\RelationshipsManager;

class TypeNotificationRelationshipManager Extends RelationshipsManager
{
	public function getAllRelationsParameters()
	{
		return [
			'show' => [
				'relations' => [
					'type' => config('schedules.models.type.controllers.show')
				]
			]
		];
	}
}