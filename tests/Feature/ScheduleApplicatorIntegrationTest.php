<?php

namespace IlBronza\Schedules\Tests\Feature;

use Exception;
use IlBronza\Schedules\Helpers\Applicators\BulkScheduleApplicatorHelper;
use IlBronza\Schedules\Helpers\Applicators\ScheduleApplicatorHelper;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\Fixtures\Models\Alpha\CollidingSchedulable as AlphaCollidingSchedulable;
use IlBronza\Schedules\Tests\Fixtures\Models\Beta\CollidingSchedulable as BetaCollidingSchedulable;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use IlBronza\Schedules\Tests\TestCase;
use RuntimeException;

class ScheduleApplicatorIntegrationTest extends TestCase
{
    use CreatesScheduleFixtures;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('schedules.applicableTo', [
            Schedulable::class => 'schedulables',
            AlphaCollidingSchedulable::class => 'alpha-colliding',
            BetaCollidingSchedulable::class => 'beta-colliding',
        ]);
    }

    public function testApplicatesScheduleFromModelValueWithNotifications(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $this->createTypeNotification($type, before: 20);
        $schedulable = $this->createSchedulable(currentValue: 90);

        $schedule = ScheduleApplicatorHelper::applicateScheduleToModel(
            $type,
            $schedulable
        );

        $this->assertSame(90.0, $schedule->getStartingValue());
        $this->assertSame(190.0, $schedule->getDeadlineValue());
        $this->assertSame($schedulable->getKey(), $schedule->schedulable_id);
        $this->assertSame(1, ScheduledNotification::query()->count());
        $this->assertSame(
            170.0,
            ScheduledNotification::query()->first()->getDeadlineValue($schedule)
        );
    }

    public function testFindOrApplicateIsIdempotent(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $this->createTypeNotification($type, before: 20);
        $schedulable = $this->createSchedulable();

        $first = ScheduleApplicatorHelper::findOrApplicateStartingScheduleToModel(
            $type,
            $schedulable,
            startingValue: 50
        );
        $notification = ScheduledNotification::query()->first();
        $oldNotificationId = $this->insertNotificationRow('old-notification-id');
        $notification->notification_id = $oldNotificationId;
        $notification->save();

        $second = ScheduleApplicatorHelper::findOrApplicateStartingScheduleToModel(
            $type,
            $schedulable,
            startingValue: 500
        );

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(500.0, $second->getStartingValue());
        $this->assertSame(600.0, $second->getDeadlineValue());
        $this->assertSame(1, Schedule::query()->count());
        $this->assertSame(1, ScheduledNotification::query()->count());
        $this->assertNull($notification->fresh()->notification_id);
        $this->assertSame(
            580.0,
            $notification->fresh()->getDeadlineValue($second)
        );

        $currentNotificationId = $this->insertNotificationRow(
            'current-notification-id'
        );
        $notification->notification_id = $currentNotificationId;
        $notification->save();

        ScheduleApplicatorHelper::findOrApplicateStartingScheduleToModel(
            $type,
            $schedulable,
            startingValue: 500
        );

        $this->assertSame(
            $currentNotificationId,
            $notification->fresh()->notification_id
        );
    }

    public function testEndingApplicationCalculatesStartingValue(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $schedulable = $this->createSchedulable();

        $schedule = ScheduleApplicatorHelper::applicateEndingScheduleToModel(
            $type,
            $schedulable,
            deadlineValue: 250
        );

        $this->assertSame(150.0, $schedule->getStartingValue());
        $this->assertSame(250.0, $schedule->getDeadlineValue());
    }

    public function testExplicitGetterOverridesConfiguredSource(): void
    {
        $type = $this->createType(
            $this->createMeasurementUnit(),
            [
                'models' => [
                    [
                        'model' => Schedulable::class,
                        'source' => 'current_reading',
                        'method' => 'getAlternateReading',
                    ],
                ],
            ]
        );
        $schedulable = $this->createSchedulable(currentValue: 90);

        $schedule = ScheduleApplicatorHelper::applicateScheduleToModel(
            $type,
            $schedulable
        );

        $this->assertSame(100.0, $schedule->getStartingValue());
    }

    public function testDuplicateCurrentScheduleFails(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $schedulable = $this->createSchedulable();

        ScheduleApplicatorHelper::applicateScheduleToModel($type, $schedulable);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('already have schedule');

        ScheduleApplicatorHelper::applicateScheduleToModel($type, $schedulable);
    }

    public function testMultipleSchedulesAreCreatedWhenTypeAllowsThem(): void
    {
        $type = $this->createType(
            $this->createMeasurementUnit(),
            ['allow_multiple' => true]
        );
        $schedulable = $this->createSchedulable();

        ScheduleApplicatorHelper::applicateScheduleToModel($type, $schedulable);
        ScheduleApplicatorHelper::applicateScheduleToModel($type, $schedulable);

        $this->assertSame(2, Schedule::query()->count());
    }

    public function testBulkApplicationDoesNotSwallowErrors(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $first = $this->createSchedulable();
        $second = $this->createSchedulable();

        ScheduleApplicatorHelper::applicateScheduleToModel($type, $first);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('already have schedule');

        BulkScheduleApplicatorHelper::applicateScheduleToModels(
            $type,
            collect([$first, $second])
        );
    }

    public function testBulkApplicationRollsBackWhenLaterElementFails(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $first = $this->createSchedulable();
        $second = $this->createSchedulable();

        ScheduleApplicatorHelper::applicateScheduleToModel($type, $second);

        $this->assertSame(1, Schedule::query()->count());

        try {
            BulkScheduleApplicatorHelper::applicateScheduleToModels(
                $type,
                collect([$first, $second])
            );

            $this->fail('The invalid bulk element did not fail');
        } catch (Exception $exception) {
            $this->assertStringContainsString(
                'already have schedule',
                $exception->getMessage()
            );
            $this->assertSame(1, Schedule::query()->count());
            $this->assertEquals(
                $second->getKey(),
                Schedule::query()->first()->schedulable_id
            );
        }
    }

    public function testNotificationFailureRollsBackSchedule(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $this->createTypeNotification($type, before: 'invalid');
        $schedulable = $this->createSchedulable();

        try {
            ScheduleApplicatorHelper::applicateScheduleToModel(
                $type,
                $schedulable
            );

            $this->fail('The invalid notification threshold did not fail');
        } catch (Exception) {
            $this->assertSame(0, Schedule::query()->count());
            $this->assertSame(0, ScheduledNotification::query()->count());
        }
    }

    public function testOnlyExactlyConfiguredFqcnCanBeApplied(): void
    {
        $type = $this->createType(
            $this->createMeasurementUnit(),
            [
                'models' => [
                    [
                        'model' => AlphaCollidingSchedulable::class,
                        'source' => 'current_reading',
                    ],
                ],
            ]
        );

        $configured = AlphaCollidingSchedulable::query()->create([
            'name' => 'Alpha vehicle',
            'current_reading' => 40,
        ]);
        $sameBasenameDifferentNamespace = BetaCollidingSchedulable::query()->create([
            'name' => 'Beta vehicle',
            'current_reading' => 40,
        ]);

        $schedule = ScheduleApplicatorHelper::applicateScheduleToModel(
            $type,
            $configured
        );

        $this->assertSame(40.0, $schedule->getStartingValue());
        $this->assertSame($configured->getKey(), $schedule->schedulable_id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Model '.BetaCollidingSchedulable::class
            .' not available for schedule '.$type->getName()
        );

        ScheduleApplicatorHelper::applicateScheduleToModel(
            $type,
            $sameBasenameDifferentNamespace
        );
    }
}
