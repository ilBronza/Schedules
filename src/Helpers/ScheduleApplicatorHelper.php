<?php

namespace IlBronza\Schedules\Helpers;

use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScheduleApplicatorHelper
{
	public Type $type;
	public Model $model;

	public function __construct(Type $type, Model $model)
	{
		$this->type = $type;
		$this->model = $model;
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

	public function getValueGetterMethod()
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

	public function getStartingValue()
	{
		$valueGetterMethod = $this->getValueGetterMethod();

		return $this->getModel()->{$valueGetterMethod}();
	}

	// public function calculateDeadlineValue()
	// {
	// 	$startingValue = $this->getStartingValue();

	// 	return $this->getType()->calculateDeadlineValue($startingValue);
	// }

	public function applicate()
	{
		$startingValue = $this->getStartingValue();


		// $deadlineValue = $this->calculateDeadlineValue();

		// dd($deadlineValue);

		$schedule = Schedule::getProjectClassName()::make();
		$schedule->setType($this->getType());


		$schedule->setStartingValue(
			$startingValue
		);

		$schedule->save();


		dd($startingValue);
	}
}
