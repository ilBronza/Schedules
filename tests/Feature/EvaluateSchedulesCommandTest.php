<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\Schedules\Contracts\ScheduleNotificationDispatcher;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\SchedulesServiceProvider;
use IlBronza\Schedules\Services\ScheduleEvaluator;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use IlBronza\Schedules\Tests\Fixtures\RecordingNotificationDispatcher;
use IlBronza\Schedules\Tests\TestCase;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule as ConsoleSchedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionMethod;
use RuntimeException;

class EvaluateSchedulesCommandTest extends TestCase
{
    use CreatesScheduleFixtures;

    public function testChunkZeroFailsWithoutSignalingSuccess(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The chunk option must be greater than zero');

        Artisan::call('schedules:evaluate', ['--chunk' => 0]);
    }

    public function testNonCurrentScheduleIsSkippedByCommand(): void
    {
        $dispatcher = $this->bindRecordingDispatcher();
        $schedule = $this->createSchedule(currentValue: 90, deadline: 100);
        $this->createScheduledNotification($schedule, deadline: 80);
        $schedule->forceFill(['expired_at' => now()])->save();

        $this->assertSame(
            Command::SUCCESS,
            Artisan::call('schedules:evaluate')
        );

        $this->assertSame(0, $dispatcher->count());
        $this->assertSame(
            ScheduledNotification::STATUS_PENDING,
            $schedule->scheduledNotifications()->first()->fresh()->status
        );
    }

    public function testNonCurrentScheduleIsSkippedByEvaluator(): void
    {
        $dispatcher = $this->bindRecordingDispatcher();
        $schedule = $this->createSchedule(currentValue: 90, deadline: 100);
        $this->createScheduledNotification($schedule, deadline: 80);
        $schedule->forceFill(['expired_at' => now()])->save();

        $this->app->make(ScheduleEvaluator::class)->evaluate($schedule->fresh());

        $this->assertSame(0, $dispatcher->count());
    }

    public function testGetterErrorFailsCommandWithoutDispatch(): void
    {
        $dispatcher = $this->bindRecordingDispatcher();
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->persistTypeWithoutModelValidation([
            'models' => [
                [
                    'model' => Schedulable::class,
                    'method' => 'getMissingReading',
                ],
            ],
        ]);
        $schedulable = $this->createSchedulable(90);
        $schedule = Schedule::query()->forceCreate([
            'type_id' => $type->getKey(),
            'schedulable_type' => $schedulable->getMorphClass(),
            'schedulable_id' => $schedulable->getKey(),
            'starting_value' => 0,
            'deadline_value' => 100,
        ]);
        $this->createScheduledNotification($schedule, deadline: 80);

        try {
            Artisan::call('schedules:evaluate');
            $this->fail('Expected getter error to stop the command');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'getMissingReading',
                $exception->getMessage()
            );
        }

        $this->assertSame(0, $dispatcher->count());
        $this->assertNull(
            $schedule->scheduledNotifications()->first()->fresh()->notification_id
        );
    }

    public function testDispatcherErrorRollsBackAndFailsCommand(): void
    {
        $this->app->instance(
            ScheduleNotificationDispatcher::class,
            new class implements ScheduleNotificationDispatcher {
                public function dispatch(
                    Schedule $schedule,
                    ScheduledNotification $scheduledNotification
                ): string {
                    throw new RuntimeException('dispatcher failed');
                }
            }
        );

        $schedule = $this->createSchedule(currentValue: 90, deadline: 100);
        $scheduledNotification = $this->createScheduledNotification(
            $schedule,
            deadline: 80
        );

        try {
            Artisan::call('schedules:evaluate');
            $this->fail('Expected dispatcher error to stop the command');
        } catch (RuntimeException $exception) {
            $this->assertSame('dispatcher failed', $exception->getMessage());
        }

        $scheduledNotification->refresh();

        $this->assertNull($scheduledNotification->notification_id);
        $this->assertNull($scheduledNotification->last_dispatched_value);
        $this->assertSame(
            ScheduledNotification::STATUS_PENDING,
            $scheduledNotification->status
        );
        $this->assertSame(0, $scheduledNotification->dispatches()->count());
        $this->assertNull($schedule->fresh()->expired_at);
    }

    public function testConsecutiveExecutionsDoNotDuplicateNotifications(): void
    {
        $dispatcher = $this->bindRecordingDispatcher();
        $schedule = $this->createSchedule(currentValue: 90, deadline: 100);
        $scheduledNotification = $this->createScheduledNotification(
            $schedule,
            deadline: 80
        );

        $this->assertSame(
            Command::SUCCESS,
            Artisan::call('schedules:evaluate')
        );
        $this->assertSame(
            Command::SUCCESS,
            Artisan::call('schedules:evaluate')
        );

        $this->assertSame(1, $dispatcher->count());
        $this->assertSame(1, $scheduledNotification->dispatches()->count());
        $this->assertSame(
            ScheduledNotification::STATUS_DISPATCHED,
            $scheduledNotification->fresh()->status
        );
    }

    public function testMultipleThresholdsAreDispatchedInDeadlineOrder(): void
    {
        $dispatcher = $this->bindOrderedRecordingDispatcher();
        $schedule = $this->createSchedule(currentValue: 95, deadline: 100);
        $later = $this->createScheduledNotification($schedule, deadline: 90);
        $earlier = $this->createScheduledNotification($schedule, deadline: 70);

        $this->assertSame(
            Command::SUCCESS,
            Artisan::call('schedules:evaluate')
        );

        $this->assertSame(
            [
                $earlier->getKey(),
                $later->getKey(),
            ],
            $dispatcher->scheduledNotificationIds()
        );
    }

    public function testAlreadyManagedScheduleIsSkipped(): void
    {
        $dispatcher = $this->bindRecordingDispatcher();
        $schedule = $this->createSchedule(currentValue: 90, deadline: 100);
        $this->createScheduledNotification($schedule, deadline: 80);
        $schedule->forceFill(['managed_at' => now()])->save();

        $this->assertSame(
            Command::SUCCESS,
            Artisan::call('schedules:evaluate')
        );

        $this->assertSame(0, $dispatcher->count());
    }

    public function testSchedulerIsNotRegisteredWhenDisabled(): void
    {
        $this->assertSame(false, config('schedules.evaluator.enabled'));

        $events = $this->evaluatorScheduleEvents(
            $this->app->make(ConsoleSchedule::class)
        );

        $this->assertCount(0, $events);
    }

    public function testSchedulerRegistersCronAndOverlapWhenEnabled(): void
    {
        config([
            'schedules.evaluator.enabled' => true,
            'schedules.evaluator.cron' => '15 */2 * * *',
            'schedules.evaluator.chunkSize' => 250,
            'schedules.evaluator.overlapExpirationMinutes' => 42,
        ]);

        $provider = new SchedulesServiceProvider($this->app);
        $register = new ReflectionMethod(
            SchedulesServiceProvider::class,
            'registerEvaluatorSchedule'
        );
        $register->setAccessible(true);
        $register->invoke($provider);

        $events = $this->evaluatorScheduleEvents(
            $this->app->make(ConsoleSchedule::class)
        );

        $this->assertCount(1, $events);

        $event = $events->first();

        $this->assertSame('15 */2 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(42, $event->expiresAt);
        $this->assertStringContainsString(
            'schedules:evaluate --chunk=250',
            $event->command
        );
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

    protected function bindOrderedRecordingDispatcher(): OrderedRecordingNotificationDispatcher
    {
        $dispatcher = new OrderedRecordingNotificationDispatcher();

        $this->app->instance(
            ScheduleNotificationDispatcher::class,
            $dispatcher
        );

        return $dispatcher;
    }

    protected function evaluatorScheduleEvents(
        ConsoleSchedule $schedule
    ): Collection {
        return collect($schedule->events())->filter(
            fn ($event): bool => is_string($event->command ?? null)
                && str_contains($event->command, 'schedules:evaluate')
        )->values();
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

}

class OrderedRecordingNotificationDispatcher implements ScheduleNotificationDispatcher
{
    protected array $dispatches = [];

    public function dispatch(
        Schedule $schedule,
        ScheduledNotification $scheduledNotification
    ): string {
        $notificationId = (string) Str::uuid();
        $now = now();

        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => static::class,
            'notifiable_type' => Schedulable::class,
            'notifiable_id' => $schedule->schedulable_id ?? 1,
            'data' => json_encode([
                'schedule_id' => $schedule->getKey(),
                'scheduled_notification_id' => $scheduledNotification->getKey(),
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->dispatches[] = [
            'scheduled_notification_id' => $scheduledNotification->getKey(),
            'notification_id' => $notificationId,
        ];

        return $notificationId;
    }

    public function scheduledNotificationIds(): array
    {
        return array_column($this->dispatches, 'scheduled_notification_id');
    }
}
