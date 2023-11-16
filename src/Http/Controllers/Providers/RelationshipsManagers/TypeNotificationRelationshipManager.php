<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\RelationshipsManagers;

use IlBronza\CRUD\Providers\RelationshipsManager\RelationshipsManager;

class TypeNotificationRelationshipManager Extends RelationshipsManager
{
	public  function getAllRelationsParameters() : array
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