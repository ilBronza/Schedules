<?php

namespace IlBronza\Schedules\Services;

use IlBronza\Schedules\Contracts\ScheduleNotificationDispatcher;
use IlBronza\Schedules\Helpers\Calculators\ScheduleValueComparator;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\TypeNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ScheduleEvaluator
{
    public function __construct(
        protected ScheduleValueComparator $comparator,
        protected ScheduleNotificationDispatcher $notificationDispatcher
    ) {
    }

    public function evaluate(Schedule $schedule): void
    {
        DB::transaction(function () use ($schedule): void {
            $this->evaluateLockedSchedule(
                $this->lockSchedule($schedule)
            );
        });
    }

    protected function lockSchedule(Schedule $schedule): Schedule
    {
        return $schedule::query()
            ->lockForUpdate()
            ->findOrFail($schedule->getKey());
    }

    protected function evaluateLockedSchedule(Schedule $schedule): void
    {
        if (! $schedule->isCurrent()) {
            return;
        }

        $schedule->loadMissing(['type.measurementUnit', 'schedulable']);

        $currentValue = $schedule->calculateCurrentValue();

        $this->evaluateCurrentState($schedule, $currentValue);
    }

    protected function evaluateCurrentState(
        Schedule $schedule,
        mixed $currentValue
    ): void {
        $this->dispatchReachedNotifications($schedule, $currentValue);
        $this->expireReachedSchedule($schedule, $currentValue);
    }

    protected function dispatchReachedNotifications(
        Schedule $schedule,
        mixed $currentValue
    ): void {
        foreach ($this->getPendingNotifications($schedule) as $notification) {
            $this->dispatchNotificationIfReached(
                $schedule,
                $notification,
                $currentValue
            );
        }
    }

    protected function getPendingNotifications(Schedule $schedule): Collection
    {
        return $schedule
            ->scheduledNotifications()
            ->pending()
            ->orderBy('deadline_value')
            ->lockForUpdate()
            ->get();
    }

    protected function dispatchNotificationIfReached(
        Schedule $schedule,
        ScheduledNotification $notification,
        mixed $currentValue
    ): void {
        if (! $this->shouldDispatch($schedule, $notification, $currentValue)) {
            return;
        }

        $notificationId = $this->notificationDispatcher->dispatch(
            $schedule,
            $notification
        );

        $notification->markDispatched($notificationId, $currentValue);
    }

    protected function shouldDispatch(
        Schedule $schedule,
        ScheduledNotification $notification,
        mixed $currentValue
    ): bool {
        if ($notification->isManaged() || $notification->isExpired()) {
            return false;
        }

        $typeNotification = $notification->getOptionalTypeNotification();
        $firstRepetition = $notification->getDeadlineValue($schedule);
        $lastRepetition = $this->resolveLastRepetition(
            $schedule,
            $typeNotification
        );

        if (! $this->comparator->hasReached(
            $schedule,
            $currentValue,
            $firstRepetition
        )) {
            return false;
        }

        if ($this->hasPassedLastRepetition(
            $schedule,
            $currentValue,
            $lastRepetition
        )) {
            return false;
        }

        $lastDispatchedValue = $notification->getLastDispatchedValue($schedule);

        if (is_null($lastDispatchedValue)) {
            return true;
        }

        if (is_null($typeNotification) || ! $typeNotification->isRepeating()) {
            return false;
        }

        $nextRepetition = $this->nextRepetitionValue(
            $schedule,
            $typeNotification,
            $lastDispatchedValue
        );

        if ($this->hasPassedLastRepetition(
            $schedule,
            $nextRepetition,
            $lastRepetition
        )) {
            return false;
        }

        return $this->comparator->hasReached(
            $schedule,
            $currentValue,
            $nextRepetition
        );
    }

    protected function resolveLastRepetition(
        Schedule $schedule,
        ?TypeNotification $typeNotification
    ): mixed {
        if (is_null($typeNotification)) {
            return $schedule->getDeadlineValue();
        }

        $lastRepetition = $typeNotification->getLastRepetition();

        if (is_null($lastRepetition)) {
            return $schedule->getDeadlineValue();
        }

        return $schedule
            ->getMeasurementUnitHelper()
            ->remove(
                $schedule->getDeadlineValue(),
                $lastRepetition
            );
    }

    protected function hasPassedLastRepetition(
        Schedule $schedule,
        mixed $currentValue,
        mixed $lastRepetition
    ): bool {
        $difference = $schedule
            ->getMeasurementUnitHelper()
            ->calculateDifference($lastRepetition, $currentValue);

        return $difference > 0;
    }

    protected function nextRepetitionValue(
        Schedule $schedule,
        TypeNotification $typeNotification,
        mixed $lastDispatchedValue
    ): mixed {
        $repeatEvery = $typeNotification->getRepeatEvery();
        $repeatingUnit = $typeNotification->getRepeatingMeasurementUnit();
        $scheduleUnit = $schedule->getType()->getMeasurementUnit();

        if ($repeatingUnit->getBaseMeasurementUnitHelper()
            !== $scheduleUnit->getBaseMeasurementUnitHelper()
        ) {
            throw new RuntimeException(
                'TypeNotification ['.$typeNotification->getKey().'] repeating measurement unit base does not match schedule type measurement unit base.'
            );
        }

        $intervalInScheduleUnit = $scheduleUnit->getFromBaseUnitValue(
            $repeatingUnit->convertToBaseUnitValue($repeatEvery)
        );

        return $schedule
            ->getMeasurementUnitHelper()
            ->add(
                $schedule->parseOutputValueByType($lastDispatchedValue),
                $intervalInScheduleUnit
            );
    }

    protected function hasReachedNotification(
        Schedule $schedule,
        ScheduledNotification $notification,
        mixed $currentValue
    ): bool {
        return $this->comparator->hasReached(
            $schedule,
            $currentValue,
            $notification->getDeadlineValue($schedule)
        );
    }

    protected function expireReachedSchedule(
        Schedule $schedule,
        mixed $currentValue
    ): void {
        if (! $this->hasReachedDeadline($schedule, $currentValue)) {
            return;
        }

        $this->expirePendingNotifications($schedule);
        $schedule->markExpired();
    }

    protected function expirePendingNotifications(Schedule $schedule): void
    {
        foreach ($this->getNotificationsToExpire($schedule) as $notification) {
            if (! $notification->isPending()) {
                continue;
            }

            $notification->markExpired();
        }
    }

    protected function getNotificationsToExpire(Schedule $schedule): Collection
    {
        return $schedule->scheduledNotifications()->get();
    }

    protected function hasReachedDeadline(
        Schedule $schedule,
        mixed $currentValue
    ): bool {
        return $this->comparator->hasReached(
            $schedule,
            $currentValue,
            $schedule->getDeadlineValue()
        );
    }
}
