<?php

namespace IlBronza\Schedules\Helpers\Applicators;

use IlBronza\FileCabinet\Models\Dossierrow;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait ScheduleApplicatorHelperGettersSettersTraits
{
	public function setSchedule(Schedule $schedule)
	{
		$this->schedule = $schedule;
	}

	public function setType(Type $type)
	{
		$this->type = $type;
	}

	public function setModel(Model $model)
	{
		$this->model = $model;
	}

	/**
	 * create and assign a new Schedule based on schedule %type
	 * 
	 **/
	public function initializeSchedule()
	{
		$this->schedule = Schedule::getProjectClassName()::make();
		$this->schedule->setType($this->getType());
	}

	public function getSchedules() : Collection
	{
		return $this->getModel()->getCurrentSchedulesByType(
			$this->getType()
		);
	}

	public function getSchedule() : ? Schedule
	{
		if(isset($this->schedule))
			return $this->schedule;

		if(! $schedule = $this->getModel()->getCurrentScheduleByType(
			$this->getType()
		))
			return null;

		$this->schedule = $schedule;

		return $this->schedule;
	}

	public function getCurrentScheduleByType() : ? Schedule
	{
		return $this->getModel()->getCurrentScheduleByType(
			$this->getType()
		);
	}

	public function modelHasSchedule() : bool
	{
		return count($this->getSchedules()) > 0;
	}

	public function checkRightsToAddSchedule()
	{
		if(! $this->getType()->allowMultiple())
			if($this->modelHasSchedule())
				throw new \Exception('Model ' . $this->getModel()->getName() . ' already have schedule ' . $this->getType()->getName());
	}

	public function getScheduledModelClassname() : string
	{
		return get_class(
			$this->getModel()
		);
	}

	public function getType() : Type
	{
		return $this->type;
	}

	public function getModel() : Model
	{
		return $this->model;
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
			if($modelParameters['model'] == $this->getScheduledModelClassname())
			{
				if($source = ($modelParameters['source'] ?? false))
					return "get" . Str::studly($source);

				if($modelParameters['method'] ?? false)
					return $modelParameters['method'];

				throw new \Exception('I can\'t calculate the current value for the module\'s schedule. You need to declare a source field or a method name');
			}

		if(($model = $this->getModel()) instanceof Dossierrow)
			if($model->getFormrow()->isExpirationDate())
				return 'getCurrentDate';

		throw new \Exception('Model ' . $this->getScheduledModelClassname() . ' not available for schedule ' . $this->getType()->getName());
	}

}
