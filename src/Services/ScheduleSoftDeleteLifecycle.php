<?php

namespace IlBronza\Schedules\Services;

use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Models\TypeNotification;
use RuntimeException;

class ScheduleSoftDeleteLifecycle
{
    public function assertTypeCanBeDeleted(Type $type): void
    {
        if ($type->schedules()->exists()) {
            throw new RuntimeException(
                'Cannot delete Type ['.$type->getKey()
                .']: schedules still reference it.'
            );
        }
    }

    public function assertTypeNotificationCanBeDeleted(
        TypeNotification $typeNotification
    ): void {
        if ($typeNotification->scheduledNotifications()->exists()) {
            throw new RuntimeException(
                'Cannot delete TypeNotification ['
                .$typeNotification->getKey()
                .']: scheduled notifications still reference it.'
            );
        }
    }

    public function cascadeSoftDeleteScheduledNotifications(
        Schedule $schedule
    ): void {
        $schedule
            ->scheduledNotifications()
            ->get()
            ->each(function (ScheduledNotification $notification): void {
                $notification->delete();
            });
    }

    public function cascadeForceDeleteScheduledNotifications(
        Schedule $schedule
    ): void {
        $schedule
            ->scheduledNotifications()
            ->withTrashed()
            ->get()
            ->each(function (ScheduledNotification $notification): void {
                $notification->forceDelete();
            });
    }

    public function cascadeRestoreScheduledNotifications(
        Schedule $schedule
    ): void {
        $schedule
            ->scheduledNotifications()
            ->onlyTrashed()
            ->get()
            ->each(function (ScheduledNotification $notification): void {
                $notification->restore();
            });
    }

    public function cascadeSoftDeleteTypeNotifications(Type $type): void
    {
        $type
            ->typeNotifications()
            ->get()
            ->each(function (TypeNotification $typeNotification): void {
                $typeNotification->delete();
            });
    }

    public function cascadeForceDeleteTypeNotifications(Type $type): void
    {
        $type
            ->typeNotifications()
            ->withTrashed()
            ->get()
            ->each(function (TypeNotification $typeNotification): void {
                $typeNotification->forceDelete();
            });
    }

    public function cascadeRestoreTypeNotifications(Type $type): void
    {
        $type
            ->typeNotifications()
            ->onlyTrashed()
            ->get()
            ->each(function (TypeNotification $typeNotification): void {
                $typeNotification->restore();
            });
    }

    public function findScheduledNotificationIncludingTrashed(
        Schedule $schedule,
        TypeNotification $typeNotification
    ): ? ScheduledNotification {
        return $schedule
            ->scheduledNotifications()
            ->withTrashed()
            ->where('type_notification_id', $typeNotification->getKey())
            ->first();
    }

    public function restoreScheduledNotificationForReuse(
        ScheduledNotification $notification
    ): ScheduledNotification {
        if ($notification->trashed()) {
            $notification->restore();
        }

        return $notification;
    }

    public function assertNoSoftDeletedUniqueConflictOnCreate(
        ScheduledNotification $notification
    ): void {
        $scheduleId = $notification->schedule_id;
        $typeNotificationId = $notification->type_notification_id;

        if (is_null($scheduleId) || is_null($typeNotificationId)) {
            return;
        }

        $trashed = ScheduledNotification::query()
            ->onlyTrashed()
            ->where('schedule_id', $scheduleId)
            ->where('type_notification_id', $typeNotificationId)
            ->first();

        if (is_null($trashed)) {
            return;
        }

        throw new RuntimeException(
            'Cannot create ScheduledNotification: soft-deleted row ['
            .$trashed->getKey()
            .'] already occupies unique pair schedule_id='
            .$scheduleId
            .', type_notification_id='
            .$typeNotificationId
            .'. Restore it instead of inserting a duplicate.'
        );
    }
}
