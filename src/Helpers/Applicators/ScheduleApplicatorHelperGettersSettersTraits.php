<?php

namespace IlBronza\Schedules\Helpers\Applicators;

use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use Illuminate\Database\Eloquent\Model;

trait ScheduleApplicatorHelperGettersSettersTraits
{
	/**
	 * create and assign a new Schedule based on schedule %type
	 * 
	 **/
	public function initializeSchedule()
	{
		$this->schedule = Schedule::getProjectClassName()::make();
		$this->schedule->setType($this->getType());
	}

	public function modelHasSchedule() : bool
	{
		return count($this->getModel()->getCurrentSchedulesByType(
			$this->getType()
		)) > 0;
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
					return "get" . Str::studly($source) . 'Attribute';

				if($modelParameters['method'] ?? false)
					return $modelParameters['method'];

				throw new \Exception('I can\'t calculate the current value for the module\'s schedule. You need to declare a source field or a method name');
			}

		throw new \Exception('Model ' . $this->getScheduledModelClassname() . ' not available for schedule ' . $this->getType()->getName());
	}

}
