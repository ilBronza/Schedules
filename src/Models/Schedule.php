<?php

namespace IlBronza\Schedules\Models;


use IlBronza\MeasurementUnits\BaseMeasurementUnitHelpers\BaseMeasurementUnitHelper;
use IlBronza\MeasurementUnits\Models\MeasurementUnit;
use IlBronza\Schedules\Helpers\Applicators\ScheduleApplicatorHelper;
use IlBronza\Schedules\Helpers\Applicators\ScheduleDeadlineCalculatorHelper;
use IlBronza\Schedules\Helpers\Applicators\ScheduleStartingCalculatorHelper;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Ukn\Facades\Ukn;
use Illuminate\Database\Eloquent\Model;
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
				return ScheduleApplicatorHelper::getCurrentValueFromSchedule($this);
			});
	}

	public function getCurrentValue() : mixed
	{
		return $this->current_value;
	}

	public function getPercentageValidityAttribute() : float
	{
		if(! $this->deadline_value)
			return null;

		if($this->deadline_value < $this->getCurrentValue())
			return 100;

		if(! $this->starting_value)
			$this->starting_value = $this->calculateStarting();

		$span = $this->deadline_value - $this->starting_value;
		$delta = $this->getCurrentValue() - $this->starting_value;

		return $delta / ($span / 100);
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
    	return $this->parseOutputValueByType(
    		$this->starting_value
    	);
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