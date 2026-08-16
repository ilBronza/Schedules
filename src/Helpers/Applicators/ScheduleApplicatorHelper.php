<?php

namespace IlBronza\Schedules\Helpers\Applicators;

use Closure;
use IlBronza\Schedules\Helpers\ScheduleTypeNotificationHelper;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ScheduleApplicatorHelper
{
    use ScheduleApplicatorHelperGettersSettersTraits;

    public Type $type;
    public Model $model;
    public Schedule $schedule;

    public function __construct(Type $type, Model $model)
    {
        $this->setType($type);
        $this->setModel($model);
    }

    public static function applicateScheduleToModel(
        Type $type,
        Model $model
    ): Schedule {
        return static::runAtomically(
            $type,
            $model,
            fn (ScheduleApplicatorHelper $helper): Schedule => $helper->createFromModelValue()
        );
    }

    public static function findOrApplicateStartingScheduleToModel(
        Type $type,
        Model $model,
        mixed $startingValue
    ): Schedule {
        return static::runAtomically(
            $type,
            $model,
            fn (ScheduleApplicatorHelper $helper): Schedule => $helper
                ->findOrApplyStartingValue($startingValue)
        );
    }

    public static function applicateStartingScheduleToModel(
        Type $type,
        Model $model,
        mixed $startingValue
    ): Schedule {
        return static::runAtomically(
            $type,
            $model,
            fn (ScheduleApplicatorHelper $helper): Schedule => $helper
                ->createFromStartingValue($startingValue)
        );
    }

    public static function findOrApplicateEndingScheduleToModel(
        Type $type,
        Model $model,
        mixed $deadlineValue
    ): Schedule {
        return static::runAtomically(
            $type,
            $model,
            fn (ScheduleApplicatorHelper $helper): Schedule => $helper
                ->findOrApplyDeadlineValue($deadlineValue)
        );
    }

    public static function applicateEndingScheduleToModel(
        Type $type,
        Model $model,
        mixed $deadlineValue
    ): Schedule {
        return static::runAtomically(
            $type,
            $model,
            fn (ScheduleApplicatorHelper $helper): Schedule => $helper
                ->createFromDeadlineValue($deadlineValue)
        );
    }

    protected static function runAtomically(
        Type $type,
        Model $model,
        Closure $operation
    ): Schedule {
        return DB::transaction(function () use ($type, $model, $operation): Schedule {
            $lockedModel = static::lockModel($model);
            $helper = new static($type, $lockedModel);

            return $operation($helper);
        });
    }

    protected static function lockModel(Model $model): Model
    {
        return $model
            ->newQuery()
            ->lockForUpdate()
            ->findOrFail($model->getKey());
    }

    public function createFromModelValue(): Schedule
    {
        return $this->createFromStartingValue(
            $this->getModelScheduleStartingValue()
        );
    }

    public function findOrApplyStartingValue(
        mixed $startingValue
    ): Schedule {
        $this->initializeExistingOrNewSchedule();

        return $this->applyStartingValue($startingValue);
    }

    public function createFromStartingValue(mixed $startingValue): Schedule
    {
        $this->initializeNewSchedule();

        return $this->applyStartingValue($startingValue);
    }

    protected function applyStartingValue(mixed $startingValue): Schedule
    {
        $this->schedule->setStarting($startingValue);
        $this->schedule->calculateDeadline(save: false);

        return $this->persistScheduleWithNotifications();
    }

    public function findOrApplyDeadlineValue(
        mixed $deadlineValue
    ): Schedule {
        $this->initializeExistingOrNewSchedule();

        return $this->applyDeadlineValue($deadlineValue);
    }

    public function createFromDeadlineValue(mixed $deadlineValue): Schedule
    {
        $this->initializeNewSchedule();

        return $this->applyDeadlineValue($deadlineValue);
    }

    protected function applyDeadlineValue(mixed $deadlineValue): Schedule
    {
        $this->schedule->setDeadline($deadlineValue);
        $this->schedule->calculateStarting(save: false);

        return $this->persistScheduleWithNotifications();
    }

    protected function initializeExistingOrNewSchedule(): void
    {
        if ($this->getSchedule()) {
            return;
        }

        $this->initializeNewSchedule();
    }

    protected function initializeNewSchedule(): void
    {
        $this->checkRightsToAddSchedule();
        $this->initializeSchedule();
    }

    public function getModelSchedulesStartingValueGetterMethod(): string
    {
        return $this->getModelSchedulesCurrentValueGetterMethod();
    }

    public function getModelScheduleStartingValue(): mixed
    {
        return $this->resolveModelScheduleCurrentValue();
    }

    protected function persistScheduleWithNotifications(): Schedule
    {
        $this->persistSchedule();
        $this->createScheduledNotifications();

        return $this->schedule;
    }

    protected function persistSchedule(): void
    {
        $this->getModel()->schedules()->save($this->schedule);
    }

    protected function createScheduledNotifications(): void
    {
        $helper = new ScheduleTypeNotificationHelper($this->schedule);

        foreach ($this->getType()->getTypeNotifications() as $typeNotification) {
            $helper->associateNotification($typeNotification);
        }
    }
}
