<?php

namespace IlBronza\Schedules\Helpers;

use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use Illuminate\Database\Eloquent\Model;

class ScheduleTypeNotificationHelper
{
	public Model $model;
	public Type $scheduleType;
	public Schedule $schedule;

	public function __construct(Schedule $schedule)
	{
		$this->schedule = $schedule;
		$this->model = $schedule->getModel();
		$this->scheduleType = $schedule->getType();

	}

	public function getScheduleType() : Type
	{
		return $this->scheduleType;
	}

	public function getSchedule() : Schedule
	{
		return $this->schedule;
	}

	public function associateNotification($typeNotification)
	{
		$this->scheduledNotification = $this->getSchedule()->scheduledNotifications()->make();

		$measurementUnit = $this->getScheduleType()->getMeasurementUnit();

        $beforeValue = $measurementUnit->getBeforeValue(
            $this->getSchedule()->getDeadlineValue(),
            $typeNotification->getBefore()
        );

		$this->scheduledNotification->setTypeNotificationId($typeNotification->getKey());
        $this->scheduledNotification->setDeadlineValue($beforeValue);

        $this->scheduledNotification->save();
	}
}
