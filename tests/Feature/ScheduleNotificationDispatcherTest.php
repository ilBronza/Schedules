<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Notifications\ScheduleThresholdReachedNotification;
use IlBronza\Schedules\Services\IlBronzaScheduleNotificationDispatcher;
use IlBronza\Schedules\Tests\Fixtures\Models\Role;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use IlBronza\Schedules\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class ScheduleNotificationDispatcherTest extends TestCase
{
    public function testDispatchCreatesParentAndChildNotificationsForRoles(): void
    {
        $roles = $this->createRoles();
        $schedule = $this->makeSchedule($roles->modelKeys());
        $scheduledNotification = $this->makeScheduledNotification();

        $notificationId = $this->dispatcher()->dispatch(
            $schedule,
            $scheduledNotification
        );

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'type' => ScheduleThresholdReachedNotification::class,
            'notifiable_type' => Role::class,
            'notifiable_id' => $roles->first()->getKey(),
            'parent_id' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => Role::class,
            'notifiable_id' => $roles->last()->getKey(),
            'parent_id' => $notificationId,
        ]);
    }

    public function testDispatchUsesConfiguredRoleOrderForParentNotification(): void
    {
        $secondConfiguredRole = Role::query()->create(['name' => 'maintenance']);
        $firstConfiguredRole = Role::query()->create(['name' => 'administrator']);

        $schedule = $this->makeSchedule([
            $firstConfiguredRole->getKey(),
            $secondConfiguredRole->getKey(),
        ]);
        $scheduledNotification = $this->makeScheduledNotification();

        $notificationId = $this->dispatcher()->dispatch(
            $schedule,
            $scheduledNotification
        );

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'type' => ScheduleThresholdReachedNotification::class,
            'notifiable_type' => Role::class,
            'notifiable_id' => $firstConfiguredRole->getKey(),
            'parent_id' => null,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => Role::class,
            'notifiable_id' => $secondConfiguredRole->getKey(),
            'parent_id' => $notificationId,
        ]);
    }

    public function testDispatchWithoutRolesFails(): void
    {
        $schedule = $this->makeSchedule([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has no notification roles');

        $this->dispatcher()->dispatch(
            $schedule,
            $this->makeScheduledNotification()
        );
    }

    public function testDispatchWithMissingRoleFails(): void
    {
        $role = Role::query()->create(['name' => 'administrator']);

        $schedule = $this->makeSchedule([
            $role->getKey(),
            $role->getKey() + 999,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'One or more schedule notification roles do not exist'
        );

        $this->dispatcher()->dispatch(
            $schedule,
            $this->makeScheduledNotification()
        );
    }

    protected function createRoles(): Collection
    {
        return new Collection([
            Role::query()->create(['name' => 'administrator']),
            Role::query()->create(['name' => 'maintenance']),
        ]);
    }

    protected function makeSchedule(array $roleIds): Schedule
    {
        $type = $this->makeType($roleIds);
        $schedulable = new Schedulable([
            'name' => 'Vehicle 1',
            'current_reading' => 90,
        ]);
        $schedulable->id = 1;

        $schedule = new Schedule();
        $schedule->setRawAttributes([
            'id' => 'schedule-id',
            'type_id' => $type->getKey(),
            'schedulable_type' => Schedulable::class,
            'schedulable_id' => $schedulable->getKey(),
        ]);
        $schedule->setRelation('type', $type);
        $schedule->setRelation('schedulable', $schedulable);

        return $schedule;
    }

    protected function makeType(array $roleIds): Type
    {
        $type = new Type();
        $type->setRawAttributes([
            'id' => 'maintenance',
            'name' => 'Maintenance',
            'roles' => json_encode(
                array_map(
                    fn (int $roleId): array => ['roles' => $roleId],
                    $roleIds
                )
            ),
        ]);

        return $type;
    }

    protected function makeScheduledNotification(): ScheduledNotification
    {
        $notification = new ScheduledNotification();
        $notification->setRawAttributes([
            'id' => 'scheduled-notification-id',
            'deadline_value' => 80,
        ]);

        return $notification;
    }

    protected function dispatcher(): IlBronzaScheduleNotificationDispatcher
    {
        return $this->app->make(
            IlBronzaScheduleNotificationDispatcher::class
        );
    }
}
