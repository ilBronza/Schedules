<?php

namespace IlBronza\Schedules\Tests\Fixtures;

use IlBronza\Schedules\Contracts\ScheduleNotificationDispatcher;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordingNotificationDispatcher implements ScheduleNotificationDispatcher
{
    protected array $dispatches = [];

    public function dispatch(
        Schedule $schedule,
        ScheduledNotification $scheduledNotification
    ): string {
        $notificationId = Str::uuid()->toString();
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
            'schedule_id' => $schedule->getKey(),
            'scheduled_notification_id' => $scheduledNotification->getKey(),
            'notification_id' => $notificationId,
        ];

        return $notificationId;
    }

    public function count(): int
    {
        return count($this->dispatches);
    }

    public function firstNotificationId(): string
    {
        return $this->dispatches[0]['notification_id'];
    }
}
