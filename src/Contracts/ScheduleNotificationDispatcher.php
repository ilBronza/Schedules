<?php

namespace IlBronza\Schedules\Contracts;

use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;

interface ScheduleNotificationDispatcher
{
    public function dispatch(
        Schedule $schedule,
        ScheduledNotification $scheduledNotification
    ): string;
}
