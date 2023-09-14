<?php

namespace IlBronza\Schedules\Helpers;

use IlBronza\Schedules\Helpers\ScheduleTypeNotificationHelper;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScheduleApplicatorHelper
{
	public Type $type;
	public Model $model;
	public Schedule $schedule;

	public function __construct(Type $type, Model $model)
	{
		$this->type = $type;
		$this->model = $model;

		$this->initializeSchedule();
	}

	static function getCurrentValueFromSchedule(Schedule $schedule)
	{
		$helper = new static($schedule->getType(), $schedule->getSchedulable());

		return $helper->getModelScheduleCurrentValue();
	}

	public function initializeSchedule()
	{
		$this->schedule = Schedule::getProjectClassName()::make();
		$this->schedule->setType($this->getType());		
	}

	public function getType() : Type
	{
		return $this->type;
	}

	public function getModel() : Model
	{
		return $this->model;
	}

	static function applicateScheduleToModel(Type $type, Model $model)
	{
		$helper = new static($type, $model);

		return $helper->applicate();
	}

	public function getModelClass() : string
	{
		return get_class($this->getModel());
	}

	/**
	 * return starting value getter method form given model
	 * 
	 * if explicitly declared return $parameters['method'] value
	 * if not declared return calculated accessor name
	 * from $parameters['source'] field,
	 * ex current_km becomes getCurrentKmAttribute()
	 * 
	 * @return string
	 **/
	public function getModelSchedulesCurrentValueGetterMethod() : string
	{
		$modelsList = $this->getType()->getModelsValue();

		foreach($modelsList as $modelParameters)
			if($modelParameters['model'] == $this->getModelClass())
			{
				if($source = ($modelParameters['source'] ?? false))
					return "get" . Str::studly($source) . 'Attribute';

				if($modelParameters['method'] ?? false)
					return $modelParameters['method'];
			}

		throw new \Exception('Model ' . $this->getModelClass() . ' not available for schedule ' . $this->getType()->getName());
	}

	public function getModelSchedulesStartingValueGetterMethod() : string
	{
		return $this->getModelSchedulesCurrentValueGetterMethod();
	}

	public function getModelScheduleStartingValue() : mixed
	{
		$valueGetterMethod = $this->getModelSchedulesStartingValueGetterMethod();

		return $this->getModel()->{$valueGetterMethod}();
	}

	public function getModelScheduleCurrentValue() : mixed
	{
		$valueGetterMethod = $this->getModelSchedulesCurrentValueGetterMethod();

		return $this->getModel()->{$valueGetterMethod}();
	}

	public function modelHasSchedule() : bool
	{
		return count($this->getModel()->getCurrentSchedulesByType(
			$this->getType()
		)) > 0;
	}

	public function applicate()
	{
		if(! $this->getType()->allowMultiple())
			if($this->modelHasSchedule())
				throw new \Exception('Model ' . $this->getModel()->getName() . ' already have schedule ' . $this->getType()->getName());

		$modelScheduleStartingValue = $this->getModelScheduleStartingValue();

		$this->schedule->setStartingValue(
			$modelScheduleStartingValue
		);

		$this->getModel()->schedules()->save($this->schedule);

		$typeNotificationHelper = new ScheduleTypeNotificationHelper(
				$this->schedule
			);

		$typeNotifications = $this->getType()->getTypeNotifications();

		foreach($typeNotifications as $typeNotification)
			$typeNotificationHelper->associateNotification($typeNotification);
	}
}
