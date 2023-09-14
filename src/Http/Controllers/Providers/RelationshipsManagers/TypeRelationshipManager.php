<?php

namespace IlBronza\Schedules\Http\Controllers\Providers\RelationshipsManagers;

use IlBronza\CRUD\Providers\RelationshipsManager;

class TypeRelationshipManager Extends RelationshipsManager
{
	public function getAllRelationsParameters()
	{
		return [
			'show' => [
				'relations' => [
					'typeNotifications' => config('schedules.models.typeNotification.controllers.index'),
					'schedules' => config('schedules.models.schedule.controllers.index')
				]
			]
		];
	}
}