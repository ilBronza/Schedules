<?php

namespace IlBronza\Schedules\Models;

use IlBronza\Schedules\Models\Type;

class Schedule extends SchedulePackageBaseModel
{
	public function type()
	{
		return $this->belongsTo(Type::getProjectClassName());
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

    public function setStartingValue(mixed $value, bool $save = false)
    {
    	$this->starting_value = $value;

    	if($save)
    		$this->save();
    }

    public function setEndingValue(mixed $value = null, bool $save = false)
    {
    	$this->ending_value = $value;

    	if($save)
    		$this->save();
    }

    public function getStartingValue() : mixed
    {
    	return $this->starting_value;
    }

    public function calculateEndingValue()
    {
    	$startingValue = $this->getStartingValue();

    	$endingValue = $this->getType()->calculateDeadlineValue($startingValue);

    	dd($endingValue);
    }

	public static function boot()
	{
		parent::boot();

		static::saving(function($schedule)
		{
			if($schedule->isDirty(['starting_value']))
				$schedule->setEndingValue(
					$schedule->calculateEndingValue()
				);
		});
	}

}