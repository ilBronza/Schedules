<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\Schedules\Contracts\ScheduleNotificationDispatcher;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Services\ScheduleEvaluator;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\Fixtures\RecordingNotificationDispatcher;
use IlBronza\Schedules\Tests\TestCase;
use RuntimeException;

class ScheduleEvaluatorIntegrationTest extends TestCase
{
    use CreatesScheduleFixtures;

    public function testEvaluatorDispatchesThresholdOnceAndExpiresSchedule(): void
    {
        $dispatcher = $this->bindRecordingDispatcher();
        $schedule = $this->createSchedule(currentValue: 90, deadline: 100);
        $scheduledNotification = $this->createScheduledNotification(
            $schedule,
            deadline: 80
        );

        $this->runEvaluator($schedule);

        $this->assertSame(1, $dispatcher->count());
        $this->assertSame(
            $dispatcher->firstNotificationId(),
            $scheduledNotification->fresh()->notification_id
        );
        $this->assertNull($schedule->fresh()->expired_at);

        $schedule->getSchedulable()->update(['current_reading' => 100]);

        $this->runEvaluator($schedule);
        $this->runEvaluator($schedule);

        $this->assertSame(1, $dispatcher->count());
        $this->assertNotNull($schedule->fresh()->expired_at);
        $this->assertSame(
            ScheduledNotification::STATUS_DISPATCHED,
            $scheduledNotification->fresh()->status
        );
    }

    public function testExpiringScheduleMarksUnsentThresholdsExpiredWithoutTouchingDispatchedOrManaged(): void
    {
        $dispatcher = $this->bindRecordingDispatcher();
        $schedule = $this->createSchedule(currentValue: 50, deadline: 100);
        $pending = $this->createScheduledNotification($schedule, deadline: 120);
        $dispatched = $this->createScheduledNotification($schedule, deadline: 40);
        $managed = $this->createScheduledNotification($schedule, deadline: 30);

        $dispatchedNotificationId = $this->insertNotificationRow(
            'already-dispatched'
        );
        $dispatched->forceFill([
            'notification_id' => $dispatchedNotificationId,
        ])->save();
        $managed->forceFill([
            'managed_at' => now(),
        ])->save();

        $schedule->getSchedulable()->update(['current_reading' => 100]);

        $this->runEvaluator($schedule);

        $this->assertSame(0, $dispatcher->count());
        $this->assertNotNull($schedule->fresh()->expired_at);
        $this->assertSame(
            ScheduledNotification::STATUS_EXPIRED,
            $pending->fresh()->status
        );
        $this->assertNotNull($pending->fresh()->expired_at);
        $this->assertSame(
            ScheduledNotification::STATUS_DISPATCHED,
            $dispatched->fresh()->status
        );
        $this->assertNull($dispatched->fresh()->expired_at);
        $this->assertSame(
            $dispatchedNotificationId,
            $dispatched->fresh()->notification_id
        );
        $this->assertSame(
            ScheduledNotification::STATUS_MANAGED,
            $managed->fresh()->status
        );
        $this->assertNull($managed->fresh()->expired_at);
    }

    public function testMarkExpiredRefusesDispatchedNotification(): void
    {
        $schedule = $this->createSchedule(currentValue: 50, deadline: 100);
        $dispatched = $this->createScheduledNotification($schedule, deadline: 40);
        $dispatched->forceFill([
            'notification_id' => $this->insertNotificationRow(
                'already-dispatched'
            ),
        ])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'ScheduledNotification ['.$dispatched->getKey()
            .'] cannot be expired because its status is [dispatched].'
        );

        $dispatched->fresh()->markExpired();
    }

    public function testMarkExpiredRefusesManagedNotification(): void
    {
        $schedule = $this->createSchedule(currentValue: 50, deadline: 100);
        $managed = $this->createScheduledNotification($schedule, deadline: 30);
        $managed->forceFill([
            'managed_at' => now(),
        ])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'ScheduledNotification ['.$managed->getKey()
            .'] cannot be expired because its status is [managed].'
        );

        $managed->fresh()->markExpired();
    }

    public function testMarkExpiredRefusesAlreadyExpiredNotification(): void
    {
        $schedule = $this->createSchedule(currentValue: 50, deadline: 100);
        $expired = $this->createScheduledNotification($schedule, deadline: 20);
        $expired->forceFill([
            'expired_at' => now(),
        ])->save();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'ScheduledNotification ['.$expired->getKey()
            .'] cannot be expired because its status is [expired].'
        );

        $expired->fresh()->markExpired();
    }

    public function testReachedThresholdIsDispatchedWhenScheduleExpiresInSameEvaluation(): void
    {
        $dispatcher = $this->bindRecordingDispatcher();
        $schedule = $this->createSchedule(currentValue: 50, deadline: 100);
        $reached = $this->createScheduledNotification($schedule, deadline: 80);
        $unreached = $this->createScheduledNotification($schedule, deadline: 150);

        $schedule->getSchedulable()->update(['current_reading' => 100]);

        $this->runEvaluator($schedule);

        $this->assertSame(1, $dispatcher->count());
        $this->assertNotNull($schedule->fresh()->expired_at);
        $this->assertSame(
            ScheduledNotification::STATUS_DISPATCHED,
            $reached->fresh()->status
        );
        $this->assertNull($reached->fresh()->expired_at);
        $this->assertSame(
            ScheduledNotification::STATUS_EXPIRED,
            $unreached->fresh()->status
        );
        $this->assertNotNull($unreached->fresh()->expired_at);
    }

    protected function bindRecordingDispatcher(): RecordingNotificationDispatcher
    {
        $dispatcher = new RecordingNotificationDispatcher();

        $this->app->instance(
            ScheduleNotificationDispatcher::class,
            $dispatcher
        );

        return $dispatcher;
    }

    protected function createSchedule(
        float $currentValue,
        float $deadline
    ): Schedule {
        $measurementUnit = $this->createMeasurementUnit();
        $type = Type::query()->find('maintenance')
            ?? $this->createType($measurementUnit);
        $schedulable = $this->createSchedulable($currentValue);

        return Schedule::query()->forceCreate([
            'type_id' => $type->getKey(),
            'schedulable_type' => $schedulable->getMorphClass(),
            'schedulable_id' => $schedulable->getKey(),
            'starting_value' => 0,
            'deadline_value' => $deadline,
        ]);
    }

    protected function runEvaluator(Schedule $schedule): void
    {
        $this->app
            ->make(ScheduleEvaluator::class)
            ->evaluate($schedule);
    }
}
