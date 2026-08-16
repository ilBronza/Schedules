<?php

namespace IlBronza\Schedules\Helpers;

use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Models\TypeNotification;
use IlBronza\Schedules\Services\ScheduleSoftDeleteLifecycle;
use Illuminate\Database\Eloquent\Model;

class ScheduleTypeNotificationHelper
{
	public Model $model;
	public Type $scheduleType;
	public Schedule $schedule;
	public ScheduledNotification $scheduledNotification;

	public function __construct(Schedule $schedule)
	{
		$this->schedule = $schedule;
		$this->model = $schedule->getSchedulable();
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

	public function associateNotification(TypeNotification $typeNotification): void
	{
		$deadlineValue = $this->calculateNotificationDeadline($typeNotification);

		if($notification = $this->findNotification($typeNotification))
		{
			$this->synchronizeNotification($notification, $deadlineValue);
			return;
		}

		$this->scheduledNotification = $this->getSchedule()->scheduledNotifications()->make();

		$this->scheduledNotification->setTypeNotificationId($typeNotification->getKey());
        $this->scheduledNotification->setDeadlineValue($deadlineValue);

        $this->scheduledNotification->save();
	}

	protected function findNotification(
		TypeNotification $typeNotification
	): ? ScheduledNotification
	{
		$lifecycle = app(ScheduleSoftDeleteLifecycle::class);

		$notification = $lifecycle->findScheduledNotificationIncludingTrashed(
			$this->getSchedule(),
			$typeNotification
		);

		if (is_null($notification)) {
			return null;
		}

		return $lifecycle->restoreScheduledNotificationForReuse($notification);
	}

	protected function synchronizeNotification(
		ScheduledNotification $notification,
		mixed $deadlineValue
	): void
	{
		if($this->notificationDeadlineMatches($notification, $deadlineValue))
			return;

		$notification->reschedule($deadlineValue);
	}

	protected function notificationDeadlineMatches(
		ScheduledNotification $notification,
		mixed $deadlineValue
	): bool
	{
		$currentDeadline = $notification->getDeadlineValue(
			$this->getSchedule()
		);

		$difference = $this->getSchedule()
			->getMeasurementUnitHelper()
			->calculateDifference($currentDeadline, $deadlineValue);

		return $difference == 0;
	}

	protected function calculateNotificationDeadline(
		TypeNotification $typeNotification
	): mixed
	{
		return $this->getSchedule()
			->getMeasurementUnitHelper()
			->remove(
				$this->getSchedule()->getDeadlineValue(),
				$typeNotification->getBefore()
			);
	}
}
