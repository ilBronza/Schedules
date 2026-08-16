<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\Schedules\Contracts\ScheduleNotificationDispatcher;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\ScheduledNotificationDispatch;
use IlBronza\Schedules\Models\TypeNotification;
use IlBronza\Schedules\Notifications\ScheduleThresholdReachedNotification;
use IlBronza\Schedules\Services\IlBronzaScheduleNotificationDispatcher;
use IlBronza\Schedules\Services\ScheduleEvaluator;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\Fixtures\Models\MeasurementUnit as CustomMeasurementUnit;
use IlBronza\Schedules\Tests\Fixtures\Models\Role;
use IlBronza\Schedules\Tests\Fixtures\RecordingNotificationDispatcher;
use IlBronza\Schedules\Tests\TestCase;
use RuntimeException;

class ScheduleRepetitionAndUrgencyTest extends TestCase
{
    use CreatesScheduleFixtures;

    public function testRepeatingThresholdDispatchesUntilLastRepetitionAndRecordsEverySend(): void
    {
        $dispatcher = $this->bindRecordingDispatcher();
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit, [
            'roles' => [
                ['roles' => Role::query()->create(['name' => 'maintenance'])->getKey()],
            ],
        ]);
        $typeNotification = TypeNotification::query()->forceCreate([
            'type_id' => $type->getKey(),
            'before' => 40,
            'last_repetition' => 10,
            'repeat_every' => 10,
            'repeat_every_measurement_unit_id' => $measurementUnit->getKey(),
            'urgency' => 7,
        ]);
        $schedule = $this->createSchedule($type, currentValue: 60, deadline: 100);
        $scheduledNotification = $this->createScheduledNotification(
            $schedule,
            $typeNotification,
            deadline: 60
        );

        $this->runEvaluator($schedule);
        $this->assertSame(1, $dispatcher->count());
        $this->assertSame(1, $scheduledNotification->dispatches()->count());

        $schedule->getSchedulable()->update(['current_reading' => 70]);
        $this->runEvaluator($schedule);
        $this->runEvaluator($schedule);
        $this->assertSame(2, $dispatcher->count());
        $this->assertSame(2, $scheduledNotification->dispatches()->count());

        $schedule->getSchedulable()->update(['current_reading' => 90]);
        $this->runEvaluator($schedule);
        $this->assertSame(3, $dispatcher->count());
        $this->assertSame(3, $scheduledNotification->dispatches()->count());

        $schedule->getSchedulable()->update(['current_reading' => 95]);
        $this->runEvaluator($schedule);
        $this->assertSame(3, $dispatcher->count());

        $dispatchIds = $scheduledNotification
            ->dispatches()
            ->orderBy('dispatched_at')
            ->pluck('notification_id')
            ->all();

        $this->assertCount(3, $dispatchIds);
        $this->assertCount(3, array_unique($dispatchIds));
        $this->assertSame(
            end($dispatchIds),
            $scheduledNotification->fresh()->notification_id
        );
    }

    public function testNonRepeatingThresholdDispatchesOnceEvenWhenReevaluated(): void
    {
        $dispatcher = $this->bindRecordingDispatcher();
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit);
        $typeNotification = $this->createTypeNotification($type, before: 20);
        $schedule = $this->createSchedule($type, currentValue: 90, deadline: 100);
        $this->createScheduledNotification(
            $schedule,
            $typeNotification,
            deadline: 80
        );

        $this->runEvaluator($schedule);
        $this->runEvaluator($schedule);

        $this->assertSame(1, $dispatcher->count());
        $this->assertSame(1, ScheduledNotificationDispatch::query()->count());
    }

    public function testUrgencyIsAppliedToNotificationPayload(): void
    {
        $roles = collect([
            Role::query()->create(['name' => 'administrator']),
        ]);
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit, [
            'roles' => [
                ['roles' => $roles->first()->getKey()],
            ],
        ]);
        $typeNotification = TypeNotification::query()->forceCreate([
            'type_id' => $type->getKey(),
            'before' => 20,
            'urgency' => 9,
        ]);
        $schedule = $this->createSchedule($type, currentValue: 90, deadline: 100);
        $scheduledNotification = $this->createScheduledNotification(
            $schedule,
            $typeNotification,
            deadline: 80
        );
        $scheduledNotification->setRelation('typeNotification', $typeNotification);

        $notification = new ScheduleThresholdReachedNotification(
            $schedule,
            $scheduledNotification
        );

        $payload = $notification->toArray($roles->first());

        $this->assertSame(9, $payload['urgency']);
        $this->assertSame(9, $notification->getPriority());

        $notificationId = $this->app
            ->make(IlBronzaScheduleNotificationDispatcher::class)
            ->dispatch($schedule, $scheduledNotification);

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'type' => ScheduleThresholdReachedNotification::class,
        ]);

        $stored = json_decode(
            $this->app['db']
                ->table('notifications')
                ->where('id', $notificationId)
                ->value('data'),
            true
        );

        $this->assertSame(9, $stored['urgency']);
    }

    public function testIncompleteRepetitionConfigurationFailsFast(): void
    {
        $typeNotification = new TypeNotification();
        $typeNotification->setRawAttributes([
            'id' => 'broken-repeat',
            'repeat_every' => 10,
            'repeat_every_measurement_unit_id' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('repeat_every_measurement_unit_id');

        $typeNotification->assertRepetitionConfigurationIsValid();
    }

    public function testDirectSaveRejectsRepeatEveryWithoutMeasurementUnit(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('repeat_every and repeat_every_measurement_unit_id');

        TypeNotification::query()->forceCreate([
            'type_id' => $type->getKey(),
            'before' => 20,
            'repeat_every' => 10,
            'repeat_every_measurement_unit_id' => null,
        ]);
    }

    public function testDirectSaveRejectsMeasurementUnitWithoutRepeatEvery(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('repeat_every and repeat_every_measurement_unit_id');

        TypeNotification::query()->forceCreate([
            'type_id' => $type->getKey(),
            'before' => 20,
            'repeat_every' => null,
            'repeat_every_measurement_unit_id' => $measurementUnit->getKey(),
        ]);
    }

    public function testDirectSaveRejectsLastRepetitionWithoutRepetition(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('last_repetition');

        TypeNotification::query()->forceCreate([
            'type_id' => $type->getKey(),
            'before' => 20,
            'last_repetition' => 5,
        ]);
    }

    public function testDirectSaveRejectsNonPositiveRepeatEvery(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('repeat_every');

        TypeNotification::query()->forceCreate([
            'type_id' => $type->getKey(),
            'before' => 20,
            'repeat_every' => 0,
            'repeat_every_measurement_unit_id' => $measurementUnit->getKey(),
        ]);
    }

    public function testDirectSaveRejectsNegativeLastRepetition(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('last_repetition');

        TypeNotification::query()->forceCreate([
            'type_id' => $type->getKey(),
            'before' => 20,
            'last_repetition' => -1,
            'repeat_every' => 5,
            'repeat_every_measurement_unit_id' => $measurementUnit->getKey(),
        ]);
    }

    public function testDirectSaveRejectsLastRepetitionFartherThanBefore(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('last_repetition');

        TypeNotification::query()->forceCreate([
            'type_id' => $type->getKey(),
            'before' => 20,
            'last_repetition' => 30,
            'repeat_every' => 5,
            'repeat_every_measurement_unit_id' => $measurementUnit->getKey(),
        ]);
    }

    public function testDirectSaveAllowsNonRepeatingNullConfiguration(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $typeNotification = TypeNotification::query()->forceCreate([
            'type_id' => $type->getKey(),
            'before' => 20,
            'last_repetition' => null,
            'repeat_every' => null,
            'repeat_every_measurement_unit_id' => null,
        ]);

        $this->assertFalse($typeNotification->isRepeating());
        $this->assertNull($typeNotification->getRepeatEvery());
    }

    public function testDirectUpdateRejectsInvalidRepetitionConfiguration(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit);
        $typeNotification = $this->createTypeNotification($type, before: 20);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('repeat_every and repeat_every_measurement_unit_id');

        $typeNotification->repeat_every = 10;
        $typeNotification->save();
    }

    public function testRepeatingMeasurementUnitUsesConfiguredMeasurementUnitSubclass(): void
    {
        config([
            'measurementunits.models.measurementUnit.class' => CustomMeasurementUnit::class,
        ]);

        $dispatcher = $this->bindRecordingDispatcher();
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit, [
            'roles' => [
                ['roles' => Role::query()->create(['name' => 'maintenance'])->getKey()],
            ],
        ]);
        $typeNotification = TypeNotification::query()->forceCreate([
            'type_id' => $type->getKey(),
            'before' => 40,
            'last_repetition' => 10,
            'repeat_every' => 10,
            'repeat_every_measurement_unit_id' => $measurementUnit->getKey(),
            'urgency' => 7,
        ]);

        $this->assertInstanceOf(CustomMeasurementUnit::class, $measurementUnit);
        $this->assertInstanceOf(
            CustomMeasurementUnit::class,
            $typeNotification->repeatingMeasurementUnit
        );
        $this->assertInstanceOf(
            CustomMeasurementUnit::class,
            $typeNotification->getRepeatingMeasurementUnit()
        );

        $schedule = $this->createSchedule($type, currentValue: 60, deadline: 100);
        $scheduledNotification = $this->createScheduledNotification(
            $schedule,
            $typeNotification,
            deadline: 60
        );

        $this->runEvaluator($schedule);
        $this->assertSame(1, $dispatcher->count());

        $schedule->getSchedulable()->update(['current_reading' => 70]);
        $this->runEvaluator($schedule);
        $this->runEvaluator($schedule);
        $this->assertSame(2, $dispatcher->count());

        $schedule->getSchedulable()->update(['current_reading' => 90]);
        $this->runEvaluator($schedule);
        $this->assertSame(3, $dispatcher->count());
        $this->assertSame(3, $scheduledNotification->dispatches()->count());
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
        $type,
        float $currentValue,
        float $deadline
    ): Schedule {
        $schedulable = $this->createSchedulable($currentValue);

        return Schedule::query()->forceCreate([
            'type_id' => $type->getKey(),
            'schedulable_type' => $schedulable->getMorphClass(),
            'schedulable_id' => $schedulable->getKey(),
            'starting_value' => 0,
            'deadline_value' => $deadline,
        ]);
    }

    protected function createScheduledNotification(
        Schedule $schedule,
        TypeNotification $typeNotification,
        float $deadline
    ): ScheduledNotification {
        return ScheduledNotification::query()->forceCreate([
            'schedule_id' => $schedule->getKey(),
            'type_notification_id' => $typeNotification->getKey(),
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
