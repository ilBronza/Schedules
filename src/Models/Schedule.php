<?php

namespace IlBronza\Schedules\Models;

use IlBronza\Schedules\Helpers\ScheduleApplicatorHelper;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
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

	public function getName()
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
		return $this->type;
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
		$span = $this->deadline_value - $this->starting_value;
		$delta = $this->getCurrentValue() - $this->starting_value;

		return $delta / ($span / 100);
	}

    public function setStartingValue(mixed $value, bool $save = false)
    {
    	$this->starting_value = $value;

    	if($save)
    		$this->save();
    }

	public function setDeadlineValue(mixed $value = null, bool $save = false)
	{
		$this->deadline_value = $value;

		if($save)
			$this->save();
	}

    public function getStartingValue() : mixed
    {
    	return $this->starting_value;
    }

    public function getDeadlineValue() : mixed
    {
    	return $this->deadline_value;
    }

    public function calculateEndingValue() : mixed
    {
    	$startingValue = $this->getStartingValue();

    	return $this->getType()->calculateDeadlineValue($startingValue);
    }

	public static function boot()
	{
		parent::boot();

		static::saving(function($schedule)
		{
			if($schedule->isDirty(['starting_value']))
				$schedule->setDeadlineValue(
					$schedule->calculateEndingValue()
				);
		});
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