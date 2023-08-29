<?php

namespace IlBronza\Schedules\Helpers;

use IlBronza\Schedules\Helpers\ScheduleApplicatorHelper;
use IlBronza\Schedules\Models\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BulkScheduleApplicatorHelper
{
	public Collection $scheduleTypes;
	public Collection $models;

	public function __construct()
	{
		$this->scheduleTypes = collect();
		$this->models = collect();
	}

	static function applicateScheduleToModels(Type $type, Collection $models)
	{
		$helper = new static();

		$helper->addScheduleType($type);
		$helper->addModels($models);

		return $helper->applicate();
	}

	public function addScheduleType(Type $type)
	{
		$this->scheduleTypes->push(
			$type
		);
	}

	public function addModels(Collection $models)
	{
		foreach($models as $model)
			$this->addModel($model);
	}

	public function addModel(Model $model)
	{
		$this->models->push($model);
	}

	public function getScheduleTypes() : Collection
	{
		return $this->scheduleTypes;
	}

	public function getModels() : Collection
	{
		return $this->models;
	}

	public function applicate()
	{
		foreach($this->getScheduleTypes() as $type)
			foreach($this->getModels() as $model)
				$this->applicateScheduleToModel($type, $model);
	}

	public function applicateScheduleToModel(Type $type, $model)
	{
		ScheduleApplicatorHelper::applicateScheduleToModel($type, $model);
	}
}
