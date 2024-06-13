<?php

namespace IlBronza\Schedules\Helpers\Applicators;

use IlBronza\Schedules\Helpers\ScheduleApplicatorHelper;
use IlBronza\Schedules\Models\Type;
use IlBronza\Ukn\Facades\Ukn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BulkScheduleApplicatorHelper
{
	public Collection $scheduleTypes;
	public Collection $models;

	public bool $debug = true;

	public function __construct()
	{
		$this->scheduleTypes = collect();
		$this->models = collect();
	}

	public function hasDebugMode() : bool
	{
		return $this->debug;
	}

	static function applicateScheduleToModels(Type $type, Collection $models)
	{
		$helper = new static();

		$helper->addScheduleType($type);
		$helper->addModels($models);

		return $helper->applicateBulk();
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

	public function applicateBulk()
	{
		foreach($this->getScheduleTypes() as $type)
			foreach($this->getModels() as $model)
				$this->applicateScheduleToModel($type, $model);
	}

	public function applicateScheduleToModel(Type $type, $model)
	{
		try
		{
			ScheduleApplicatorHelper::applicateScheduleToModel($type, $model);
		}
		catch(\Exception $e)
		{
			$errorMessage = 'Can\'t applicate schedule ' . $type->getName() . ' to ' . $model->getName() . ' because ' . $e->getMessage();

			if($this->hasDebugMode())
				throw new \Exception($errorMessage);

			Ukn::e($errorMessage);
			Log::critical($errorMessage);
		}
	}
}
