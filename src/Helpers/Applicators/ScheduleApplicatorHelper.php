<?php

namespace IlBronza\Schedules\Helpers\Applicators;

use Carbon\Carbon;
use IlBronza\Schedules\Helpers\Applicators\ScheduleApplicatorHelperGettersSettersTraits;
use IlBronza\Schedules\Helpers\ScheduleTypeNotificationHelper;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

use function dd;

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
		$this->setType($type);
		$this->setModel($model);
	}

	public function applicateSchedule() : static
	{
		$this->checkRightsToAddSchedule();

		$this->initializeSchedule();

		return $this;
	}

//	/**
//	 * this function get the current schedule value
//	 * to be used to calculate the remaining time
//	 * or the completion percentage
//	 *
//	 * @param  Schedule  $schedule
//	 *
//	 * @return mixed
//	 */
//	static function getCurrentValueFromSchedule(Schedule $schedule)
//	{
//		// dd('cossa serve questo');
//
//		$helper = new static($schedule->getType(), $schedule->getSchedulable());
//
//		return $helper->getModelScheduleCurrentValue();
//	}

	public function getModelSchedulesStartingValueGetterMethod() : string
	{
		return $this->getModelSchedulesCurrentValueGetterMethod();
	}

	public function getModelScheduleStartingValue() : mixed
	{
		$valueGetterMethod = $this->getModelSchedulesStartingValueGetterMethod();

		return $this->getModel()->{$valueGetterMethod}();
	}

//	public function getModelScheduleCurrentValue() : mixed
//	{
//		$valueGetterMethod = $this->getModelSchedulesCurrentValueGetterMethod();
//
//		return $this->getModel()->{$valueGetterMethod}();
//	}

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
		$helper->applicateSchedule();

		$modelScheduleStartingValue = $helper->getModelScheduleStartingValue();

		$helper->schedule->setStarting(
			$modelScheduleStartingValue
		);

		$helper->schedule->calculateDeadline();

		return $helper->associateScheduleAndNotifications();
	}

	static function findOrApplicateStartingScheduleToModel(Type $type, Model $model, Carbon $startingTime)
	{
		$helper = new static($type, $model);

		if(! $helper->getSchedule())
			$helper->applicateSchedule();
			dd('creare schedule con applicator applicateEndingScheduleToModel');

		$helper->schedule->setStarting(
			$startingTime,
			true
		);

		return $helper->associateScheduleAndNotifications();
	}

	public static function applicateStartingScheduleToModel(Type $type, Model $model, Carbon $startingTime)
	{
		$helper = new static($type, $model);
		$helper->applicateSchedule();

		$helper->schedule->setStarting(
			$startingTime,
			true
		);

		$helper->schedule->calculateDeadline();

		return $helper->associateScheduleAndNotifications();
	}

	static function findOrApplicateEndingScheduleToModel(Type $type, Model $model, Carbon $deadline)
	{
		$helper = new static($type, $model);

		if(! $helper->getSchedule())
			$helper->applicateSchedule();
			// dd('creare schedule con applicator applicateEndingScheduleToModel');

		$helper->getSchedule()->setDeadline(
			$deadline,
			$save = true
		);

		return $helper->associateScheduleAndNotifications();
	}

	public static function applicateEndingScheduleToModel(Type $type, Model $model, Carbon $deadline)
	{
		$helper = new static($type, $model);
		$helper->applicateSchedule();

		$helper->getSchedule()->setDeadline(
			$deadline,
			$save = true
		);

		return $helper->associateScheduleAndNotifications();
	}


}
