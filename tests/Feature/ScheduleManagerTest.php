<?php

namespace IlBronza\Schedules\Tests\Feature;

use Carbon\Carbon;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Services\ScheduleManager;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\TestCase;

class ScheduleManagerTest extends TestCase
{
    use CreatesScheduleFixtures;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testManageMarksScheduleAndNotificationsAtSameTime(): void
    {
        Carbon::setTestNow('2026-08-14 14:00:00');

        $schedule = $this->createSchedule();
        $notifications = $this->createScheduledNotifications($schedule);

        $managedSchedule = $this->manager()->manage($schedule);

        $this->assertSame(
            '2026-08-14 14:00:00',
            $managedSchedule->managed_at->toDateTimeString()
        );
        $this->assertSame(
            Schedule::STATUS_MANAGED,
            $managedSchedule->status
        );

        foreach ($notifications as $notification) {
            $this->assertSame(
                '2026-08-14 14:00:00',
                $notification->fresh()->managed_at->toDateTimeString()
            );
        }
    }

    public function testManageIsIdempotent(): void
    {
        Carbon::setTestNow('2026-08-14 14:00:00');
        $schedule = $this->createSchedule();

        $this->manager()->manage($schedule);

        Carbon::setTestNow('2026-08-14 15:00:00');

        $managedAgain = $this->manager()->manage($schedule);

        $this->assertSame(
            '2026-08-14 14:00:00',
            $managedAgain->managed_at->toDateTimeString()
        );
    }

    public function testBulkHttpActionMarksSelectedSchedules(): void
    {
        $first = $this->createSchedule();
        $second = $this->createSchedule();

        $response = $this
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.schedules.manageMany'),
                ['ids' => [$first->getKey(), $second->getKey()]]
            );

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'ibaction' => 'reloadTable',
            ]);

        $this->assertNotNull($first->fresh()->managed_at);
        $this->assertNotNull($second->fresh()->managed_at);
    }

    public function testSingleHttpActionMarksSchedule(): void
    {
        $schedule = $this->createSchedule();

        $response = $this
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.schedules.manage', $schedule)
            );

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'schedule_id' => $schedule->getKey(),
            ]);

        $this->assertNotNull($schedule->fresh()->managed_at);
    }

    protected function createSchedule(): Schedule
    {
        $type = Type::query()->find('maintenance')
            ?? $this->createType($this->createMeasurementUnit());
        $schedulable = $this->createSchedulable();

        return Schedule::query()->forceCreate([
            'type_id' => $type->getKey(),
            'schedulable_type' => $schedulable->getMorphClass(),
            'schedulable_id' => $schedulable->getKey(),
            'starting_value' => 0,
            'deadline_value' => 100,
        ]);
    }

    protected function createScheduledNotifications(
        Schedule $schedule
    ): array {
        return [
            $this->createScheduledNotification($schedule, 80),
            $this->createScheduledNotification($schedule, 90),
        ];
    }

    protected function manager(): ScheduleManager
    {
        return $this->app->make(ScheduleManager::class);
    }
}
