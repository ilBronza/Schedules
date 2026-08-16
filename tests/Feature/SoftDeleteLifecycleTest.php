<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\Schedules\Helpers\Applicators\ScheduleApplicatorHelper;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Models\TypeNotification;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\TestCase;
use RuntimeException;

class SoftDeleteLifecycleTest extends TestCase
{
    use CreatesScheduleFixtures;

    public function testTypeDeleteIsRestrictedWhileNonDeletedSchedulesExist(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $this->createTypeNotification($type, before: 20);
        $schedulable = $this->createSchedulable();

        ScheduleApplicatorHelper::applicateScheduleToModel($type, $schedulable);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot delete Type .* schedules still reference/');

        $type->delete();
    }

    public function testTypeNotificationDeleteIsRestrictedWhileScheduledNotificationsExist(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $typeNotification = $this->createTypeNotification($type, before: 20);
        $schedulable = $this->createSchedulable();

        ScheduleApplicatorHelper::applicateScheduleToModel($type, $schedulable);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/Cannot delete TypeNotification .* scheduled notifications still reference/'
        );

        $typeNotification->delete();
    }

    public function testScheduleSoftDeleteCascadesToScheduledNotifications(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $this->createTypeNotification($type, before: 20);
        $schedulable = $this->createSchedulable();

        $schedule = ScheduleApplicatorHelper::applicateScheduleToModel(
            $type,
            $schedulable
        );
        $notification = ScheduledNotification::query()->first();

        $schedule->delete();

        $this->assertTrue($schedule->fresh()->trashed());
        $this->assertTrue($notification->fresh()->trashed());
        $this->assertSame(0, Schedule::query()->count());
        $this->assertSame(0, ScheduledNotification::query()->count());
    }

    public function testScheduleRestoreCascadesToScheduledNotifications(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $this->createTypeNotification($type, before: 20);
        $schedulable = $this->createSchedulable();

        $schedule = ScheduleApplicatorHelper::applicateScheduleToModel(
            $type,
            $schedulable
        );
        $notification = ScheduledNotification::query()->first();

        $schedule->delete();
        $schedule->restore();

        $this->assertFalse($schedule->fresh()->trashed());
        $this->assertFalse($notification->fresh()->trashed());
        $this->assertSame(1, Schedule::query()->count());
        $this->assertSame(1, ScheduledNotification::query()->count());
    }

    public function testReapplicationRestoresSoftDeletedScheduledNotificationWithoutDuplicateKey(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $this->createTypeNotification($type, before: 20);
        $schedulable = $this->createSchedulable();

        $schedule = ScheduleApplicatorHelper::applicateScheduleToModel(
            $type,
            $schedulable
        );
        $notification = ScheduledNotification::query()->first();
        $notificationId = $notification->getKey();

        $notification->delete();

        $this->assertTrue($notification->fresh()->trashed());

        $reapplied = ScheduleApplicatorHelper::findOrApplicateStartingScheduleToModel(
            $type,
            $schedulable,
            startingValue: 90
        );

        $this->assertSame($schedule->getKey(), $reapplied->getKey());
        $this->assertSame(1, ScheduledNotification::query()->count());
        $this->assertSame(
            $notificationId,
            ScheduledNotification::query()->first()->getKey()
        );
        $this->assertFalse(
            ScheduledNotification::query()->first()->trashed()
        );
        $this->assertSame(
            1,
            ScheduledNotification::withTrashed()->count()
        );
    }

    public function testCurrentScheduleCannotLoadSoftDeletedType(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $schedulable = $this->createSchedulable();

        $schedule = ScheduleApplicatorHelper::applicateScheduleToModel(
            $type,
            $schedulable
        );

        Type::query()->whereKey($type->getKey())->update([
            'deleted_at' => now(),
        ]);
        $schedule->unsetRelation('type');
        cache()->flush();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/Schedule .* references missing type_id/'
        );

        $schedule->fresh()->getType();
    }

    public function testTypeCanBeDeletedAfterSchedulesAreRemoved(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $this->createTypeNotification($type, before: 20);
        $schedulable = $this->createSchedulable();

        $schedule = ScheduleApplicatorHelper::applicateScheduleToModel(
            $type,
            $schedulable
        );

        $schedule->forceDelete();

        $type->delete();

        $this->assertTrue($type->fresh()->trashed());
        $this->assertSame(
            0,
            TypeNotification::query()->count()
        );
        $this->assertSame(
            1,
            TypeNotification::onlyTrashed()->count()
        );
    }
}
