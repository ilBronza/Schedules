<?php

namespace IlBronza\Schedules\Models;


use IlBronza\MeasurementUnits\BaseMeasurementUnitHelpers\BaseMeasurementUnitHelper;
use IlBronza\MeasurementUnits\Models\MeasurementUnit;
use IlBronza\Schedules\Helpers\Applicators\ScheduleApplicatorHelper;
use IlBronza\Schedules\Helpers\Applicators\ScheduleDeadlineCalculatorHelper;
use IlBronza\Schedules\Helpers\Applicators\ScheduleStartingCalculatorHelper;
use IlBronza\Schedules\Helpers\Calculators\ScheduleCurrentValueCalculatorHelper;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Ukn\Facades\Ukn;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Collection;

use function is_array;

// use IlBronza\Schedules\Models\TypeNotification;

class Schedule extends SchedulePackageBaseModel
{
	public function schedulable()
	{
		return $this->morphTo();
	}

	public function getSchedulable() : Model
	{
		return $this->schedulable;
	}

	public function type()
	{
		return $this->belongsTo(Type::getProjectClassName());
	}

	public function getMeasurementUnit() : MeasurementUnit
	{
		return $this->getType()->getMeasurementUnit();
	}

	public function getName() : ? string
	{
		return $this->getType()->getName();
	}

	public function isExpired() : bool
	{
		if(! $deadlineValue = $this->getDeadlineValue())
			return null;

		return $deadlineValue < $this->getCurrentValue();
	}

	public function getPercentageValidityLimit() : int
	{
		return $this->percentage_validity ?? 95;
	}

	public function isExpiring() : bool
	{
		return $this->getPercentageValidityAttribute() > $this->getPercentageValidityLimit();
	}

	// public function typeNotifications()
	// {
	// 	return $this->hasMany(TypeNotification::class);
	// }

	public function scheduledNotifications()
	{
		return $this->hasMany(ScheduledNotification::class);
	}

	public function typeNotifications()
	{
		// hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null)
		return $this->hasMany(TypeNotification::getProjectClassName(), 'type_id', 'type_id');
	}

	public function getType() : Type
	{
		if($this->relationLoaded('type'))
			return $this->type;

		return Type::getProjectClassName()::findCached($this->type_id);
	}

	public function setType(Type $type)
	{
		$this->setRelation('type', $type);

		$typeForeignKey = $this->type()->getForeignKeyName();

		$this->$typeForeignKey = $type->getKey();
	}

	public function getCurrentValueAttribute() : mixed
	{
		return cache()->remember(
			$this->cacheKey('current_value'),
			60,
			function ()
			{
				return $this->getMeasurementUnit()->getHelper()->parseMeasurementUnitOutputValue(
					ScheduleCurrentValueCalculatorHelper::getCurrentValueFromSchedule($this)
				);
			});
	}

	public function getCurrentValue() : mixed
	{
		return $this->current_value;
	}

	public function getPercentageValidityAttribute() : float
	{
		if(! $deadlineValue = $this->getDeadlineValue())
			return null;

		if($deadlineValue < $this->getCurrentValue())
			return 100;

		if(! $startingValue = $this->getStartingValue())
			$startingValue = $this->calculateStarting();

		$span = $this->getMeasurementUnitHelper()->calculateDifference($startingValue, $deadlineValue);

		$delta = $this->getMeasurementUnitHelper()->calculateDifference($startingValue, $this->getCurrentValue());

		return $delta / ($span / 100);
	}

	public function  getPercentageValidity(bool $forceCalculation = false)
	{
		if($forceCalculation)
			return $this->percentage_validity;

		return $this->percentage_validity;

		return cache()->remember(
			$this->cacheKey('percentage_validity'),
			3600,
			function () { return $this->percentage_validity; });
	}

    public function setStarting(mixed $value, bool $save = false)
    {
    	$this->starting_value = $value;

    	if($save)
    		$this->save();
    }

	public function setDeadline(mixed $value = null, bool $save = false)
	{
		if(($this->deadline_value)&&($this->deadline_value != $value))
			if(config('schedules.notifyWhenDeadlineChanges', true))
				Ukn::w(__('schedules::messages.deadlineChangedToForElement', ['deadline' => $value, 'element' => $this->getModel()->getName()]));

		$this->deadline_value = $value;

		if($save)
			$this->save();
	}

	public function getMeasurementUnitHelper() : BaseMeasurementUnitHelper
	{
		return $this->getType()->getMeasurementUnit()->getHelper();
	}

	public function parseOutputValueByType(mixed $value) : mixed
	{
		$helper = $this->getMeasurementUnitHelper();

		return $helper->parseMeasurementUnitOutputValue($value);
	}

    public function getStartingValue() : mixed
    {
		try
		{
			return $this->parseOutputValueByType(
				$this->starting_value
			);
		}
		catch(\Exception $e)
		{
			if($e->getCode() == 9901)
				return null;

			throw $e;
		}
    }

    public function getDeadlineValue() : mixed
    {
    	return $this->parseOutputValueByType(
    		$this->deadline_value
    	);
    }

    public function calculateDeadline() : mixed
    {
    	$result = ScheduleDeadlineCalculatorHelper::calculateFromSchedule(
			$this
		);

		$this->setDeadline($result, true);

		return $result;
    }

    public function calculateStarting() : mixed
    {
		$result = ScheduleStartingCalculatorHelper::calculateFromSchedule(
			$this
		);

		$this->setStarting($result, true);

		return $result;
    }

	public function scopeNotExpired($query)
	{
		$query->whereNull('expired_at');
	}

	public function scopeNotManaged($query)
	{
		$query->whereNull('managed_at');
	}

	public function scopeCurrent($query)
	{
		$query->notExpired()->notManaged();
	}

	public function scopeByTypes($query, array|Collection $types)
	{
		if(! is_array($types))
			$types = $types->pluck('id');

		$query->whereIn('type_id', $types);
	}

	public function scopeByType($query, Type $type)
	{
		$query->byTypeId(
			$type->getKey()
		);
	}

	public function scopeByTypeId($query, string $typeId)
	{
		$query->where('type_id', $typeId);
	}
}