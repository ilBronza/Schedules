<?php

namespace IlBronza\Schedules\Tests\Unit;

use IlBronza\MeasurementUnits\BaseMeasurementUnitHelpers\BaseMeasurementUnitHelper;
use IlBronza\Schedules\Contracts\ScheduleNotificationDispatcher;
use IlBronza\Schedules\Helpers\Calculators\ScheduleValueComparator;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Services\ScheduleEvaluator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ScheduleEvaluatorTest extends TestCase
{
    public function testReachedNotificationIsDispatchedAndLinked(): void
    {
        $schedule = $this->schedule(deadline: 200);
        $notification = $this->notification($schedule, deadline: 100);
        $dispatcher = $this->createMock(ScheduleNotificationDispatcher::class);

        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($schedule, $notification)
            ->willReturn('notification-id');

        $notification
            ->expects($this->once())
            ->method('markDispatched')
            ->with('notification-id', 120);

        $this->evaluator($dispatcher, collect([$notification]))
            ->evaluateState($schedule, currentValue: 120);
    }

    public function testScheduleIsExpiredWhenDeadlineIsReached(): void
    {
        $schedule = $this->schedule(deadline: 200);
        $schedule
            ->expects($this->once())
            ->method('markExpired');

        $dispatcher = $this->createMock(ScheduleNotificationDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $this->evaluator($dispatcher)
            ->evaluateState($schedule, currentValue: 200);
    }

    public function testPendingNotificationsAreExpiredWhenScheduleDeadlineIsReached(): void
    {
        $schedule = $this->schedule(deadline: 200);
        $pending = $this->notification($schedule, deadline: 250);
        $dispatcher = $this->createMock(ScheduleNotificationDispatcher::class);

        $dispatcher->expects($this->never())->method('dispatch');
        $pending->expects($this->never())->method('markDispatched');
        $pending->expects($this->once())->method('markExpired');
        $schedule->expects($this->once())->method('markExpired');

        $this->evaluator($dispatcher, collect([$pending]))
            ->evaluateState($schedule, currentValue: 200);
    }

    public function testDispatchedAndManagedNotificationsAreNotExpiredWhenScheduleExpires(): void
    {
        $schedule = $this->schedule(deadline: 200);
        $dispatched = $this->notification(
            $schedule,
            deadline: 100,
            dispatched: true
        );
        $managed = $this->notification(
            $schedule,
            deadline: 150,
            managed: true
        );
        $dispatcher = $this->createMock(ScheduleNotificationDispatcher::class);

        $dispatcher->expects($this->never())->method('dispatch');
        $dispatched->expects($this->never())->method('markExpired');
        $managed->expects($this->never())->method('markExpired');
        $schedule->expects($this->once())->method('markExpired');

        $this->evaluator($dispatcher, collect([$dispatched, $managed]))
            ->evaluateState($schedule, currentValue: 200);
    }

    public function testNothingChangesBeforeAnyThresholdIsReached(): void
    {
        $schedule = $this->schedule(deadline: 200);
        $notification = $this->notification($schedule, deadline: 100);
        $dispatcher = $this->createMock(ScheduleNotificationDispatcher::class);

        $dispatcher->expects($this->never())->method('dispatch');
        $notification->expects($this->never())->method('markDispatched');
        $notification->expects($this->never())->method('markExpired');
        $schedule->expects($this->never())->method('markExpired');

        $this->evaluator($dispatcher, collect([$notification]))
            ->evaluateState($schedule, currentValue: 80);
    }

    protected function evaluator(
        ScheduleNotificationDispatcher $dispatcher,
        ?Collection $notifications = null
    ): TestScheduleEvaluator {
        return new TestScheduleEvaluator(
            new ScheduleValueComparator(),
            $dispatcher,
            $notifications ?? collect()
        );
    }

    protected function schedule(int $deadline): Schedule
    {
        $schedule = $this->createMock(Schedule::class);
        $schedule
            ->method('getMeasurementUnitHelper')
            ->willReturn($this->measurementUnitHelper());
        $schedule
            ->method('getDeadlineValue')
            ->willReturn($deadline);

        return $schedule;
    }

    protected function notification(
        Schedule $schedule,
        int $deadline,
        bool $dispatched = false,
        bool $managed = false,
        bool $expired = false
    ): ScheduledNotification {
        $notification = $this->createMock(ScheduledNotification::class);
        $notification
            ->method('getDeadlineValue')
            ->with($schedule)
            ->willReturn($deadline);
        $notification
            ->method('isDispatched')
            ->willReturn($dispatched);
        $notification
            ->method('isManaged')
            ->willReturn($managed);
        $notification
            ->method('isExpired')
            ->willReturn($expired);
        $notification
            ->method('isPending')
            ->willReturn(! $dispatched && ! $managed && ! $expired);
        $notification
            ->method('getOptionalTypeNotification')
            ->willReturn($this->typeNotification());
        $notification
            ->method('getLastDispatchedValue')
            ->willReturn($dispatched ? $deadline : null);

        return $notification;
    }

    protected function typeNotification(): \IlBronza\Schedules\Models\TypeNotification
    {
        $typeNotification = $this->createMock(
            \IlBronza\Schedules\Models\TypeNotification::class
        );
        $typeNotification->method('getLastRepetition')->willReturn(null);
        $typeNotification->method('isRepeating')->willReturn(false);

        return $typeNotification;
    }

    protected function measurementUnitHelper(): BaseMeasurementUnitHelper
    {
        $helper = $this->createMock(BaseMeasurementUnitHelper::class);
        $helper
            ->method('calculateDifference')
            ->willReturnCallback(
                fn (mixed $start, mixed $end): mixed => $end - $start
            );

        return $helper;
    }
}

class TestScheduleEvaluator extends ScheduleEvaluator
{
    public function __construct(
        ScheduleValueComparator $comparator,
        ScheduleNotificationDispatcher $notificationDispatcher,
        protected Collection $notifications
    ) {
        parent::__construct($comparator, $notificationDispatcher);
    }

    public function evaluateState(
        Schedule $schedule,
        mixed $currentValue
    ): void {
        $this->evaluateCurrentState($schedule, $currentValue);
    }

    protected function getPendingNotifications(Schedule $schedule): Collection
    {
        return $this->notifications;
    }

    protected function getNotificationsToExpire(Schedule $schedule): Collection
    {
        return $this->notifications;
    }
}
