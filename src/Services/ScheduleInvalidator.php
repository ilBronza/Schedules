<?php

namespace IlBronza\Schedules\Services;

use IlBronza\Schedules\Models\Schedule;
use Illuminate\Support\Facades\DB;

class ScheduleInvalidator
{
    public function invalidate(Schedule $schedule): bool
    {
        return DB::transaction(function () use ($schedule): bool {
            $lockedSchedule = $schedule::query()
                ->lockForUpdate()
                ->findOrFail($schedule->getKey());

            $lockedSchedule
                ->scheduledNotifications()
                ->lockForUpdate()
                ->get();

            $lockedSchedule->delete();

            return true;
        });
    }
}
