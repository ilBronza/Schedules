<?php

namespace IlBronza\Schedules\Helpers\Applicators;

use Carbon\Carbon;
use IlBronza\Schedules\Helpers\Applicators\ScheduleApplicatorHelperGettersSettersTraits;
use IlBronza\Schedules\Helpers\ScheduleTypeNotificationHelper;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScheduleApplicatorHelper
{
	/**
	 * usage:
	 * 
	 * 
	 * 
	 * 
	 * 
	 * 
	 * 
	 * 
	 **/



	use ScheduleApplicatorHelperGettersSettersTraits;

	public Type $type;
	public Model $model;
	public Schedule $schedule;

	/**
	 * assign schedule type and model to helper
	 * 
	 * @param Type $type
	 * @param Model $model
	 * 
	 * then initialize a new schedule model
	 * 
	 **/
	public function __construct(Type $type, Model $model)
	{
		$this->type = $type;
		$this->model = $model;

		$this->checkRightsToAddSchedule();

		$this->initializeSchedule();
	}

	static function getCurrentValueFromSchedule(Schedule $schedule)
	{
		// dd('cossa serve questo');

		$helper = new static($schedule->getType(), $schedule->getSchedulable());

		return $helper->getModelScheduleCurrentValue();
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

	public function associateScheduleAndNotifications() : Schedule
	{
		$this->getModel()->schedules()->save($this->schedule);

		$typeNotificationHelper = new ScheduleTypeNotificationHelper(
				$this->schedule
			);

		$typeNotifications = $this->getType()->getTypeNotifications();

		foreach($typeNotifications as $typeNotification)
			$typeNotificationHelper->associateNotification($typeNotification);

		return $this->schedule;
	}
































	public static function applicateScheduleToModel(Type $type, Model $model)
	{
		$helper = new static($type, $model);

		$modelScheduleStartingValue = $helper->getModelScheduleStartingValue();

		$helper->schedule->setStarting(
			$modelScheduleStartingValue
		);

		$helper->schedule->calculateDeadline();

		return $helper->associateScheduleAndNotifications();
	}

	public static function applicateStartingScheduleToModel(Type $type, Model $model, Carbon $startingTime)
	{
		$helper = new static($type, $model);

		$helper->schedule->setStarting(
			$startingTime
		);

		$helper->schedule->calculateDeadline();

		return $helper->associateScheduleAndNotifications();
	}

	public static function applicateEndingScheduleToModel(Type $type, Model $model, Carbon $deadline)
	{
		$helper = new static($type, $model);

		$helper->schedule->setDeadline(
			$deadline,
			$save = true
		);

		return $helper->associateScheduleAndNotifications();
	}


}
