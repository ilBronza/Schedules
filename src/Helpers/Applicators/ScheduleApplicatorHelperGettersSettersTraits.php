<?php

namespace IlBronza\Schedules\Helpers\Applicators;

use IlBronza\Schedules\Contracts\Schedulable;
use IlBronza\Schedules\Integrations\LegacyDossierrowScheduleValueGetter;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Services\TypeModelsConfigurationValidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;

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
	public function initializeSchedule(): void
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
		$this->schedule->setRelation('type', $this->getType());
		$this->schedule->setRelation('schedulable', $this->getModel());

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
		return $this->getSchedules()->isNotEmpty();
	}

	public function checkRightsToAddSchedule(): void
	{
		if($this->getType()->allowMultiple())
			return;

		if(! $this->modelHasSchedule())
			return;

		throw new RuntimeException(
			'Model ' . $this->getModel()->getName()
			. ' already have schedule ' . $this->getType()->getName()
		);
	}

	public function getScheduledModelClassname() : string
	{
		return $this->getModel()->getMorphClass();
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
		$validator = app(TypeModelsConfigurationValidator::class);

		foreach($modelsList as $modelParameters)
			if($this->modelParametersMatchScheduledModel($modelParameters))
				return $validator->resolveValueGetterMethod($modelParameters);

		throw new RuntimeException(
			'Model ' . $this->getScheduledModelClassname()
			. ' not available for schedule ' . $this->getType()->getName()
		);
	}

	public function resolveModelScheduleCurrentValue(): mixed
	{
		$model = $this->getModel();
		$legacyGetter = new LegacyDossierrowScheduleValueGetter();

		if ($this->modelIsConfiguredOnType()) {
			if (! $model instanceof Schedulable) {
				throw new RuntimeException(
					'Model ' . get_class($model)
					. ' must implement ' . Schedulable::class
					. ' to resolve schedule values'
				);
			}

			return $model->getScheduleCurrentValue(
				$this->getModelSchedulesCurrentValueGetterMethod()
			);
		}

		if ($legacyGetter->supports($model)) {
			return $legacyGetter->getCurrentValue($model);
		}

		throw new RuntimeException(
			'Model ' . $this->getScheduledModelClassname()
			. ' not available for schedule ' . $this->getType()->getName()
		);
	}

	protected function modelIsConfiguredOnType(): bool
	{
		foreach ($this->getType()->getModelsValue() as $modelParameters) {
			if ($this->modelParametersMatchScheduledModel($modelParameters)) {
				return true;
			}
		}

		return false;
	}

	protected function modelParametersMatchScheduledModel(array $modelParameters): bool
	{
		if(! isset($modelParameters['model']))
			throw new RuntimeException('Schedule model configuration is missing the model class');

		$configuredModel = ltrim($modelParameters['model'], '\\');
		$scheduledModel = ltrim(get_class($this->getModel()), '\\');

		return $configuredModel === $scheduledModel;
	}

}
