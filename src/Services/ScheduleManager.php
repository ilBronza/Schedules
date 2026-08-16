<?php

namespace IlBronza\Schedules\Services;

use Carbon\CarbonInterface;
use IlBronza\Schedules\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ScheduleManager
{
    public function manage(Schedule $schedule): Schedule
    {
        return DB::transaction(function () use ($schedule): Schedule {
            return $this->manageLockedSchedule(
                $this->lockSchedule($schedule)
            );
        });
    }

    public function manageMany(array $scheduleIds): Collection
    {
        return DB::transaction(function () use ($scheduleIds): Collection {
            $schedules = $this->lockSchedules($scheduleIds);

            $this->ensureAllSchedulesExist($scheduleIds, $schedules);

            return $this->manageSchedules($schedules);
        });
    }

    protected function lockSchedule(Schedule $schedule): Schedule
    {
        return $schedule::query()
            ->lockForUpdate()
            ->findOrFail($schedule->getKey());
    }

    protected function lockSchedules(array $scheduleIds): Collection
    {
        return Schedule::getProjectClassName()::query()
            ->whereKey($scheduleIds)
            ->orderBy((new Schedule())->getKeyName())
            ->lockForUpdate()
            ->get();
    }

    protected function ensureAllSchedulesExist(
        array $scheduleIds,
        Collection $schedules
    ): void {
        $expectedCount = count(array_unique($scheduleIds));

        if ($schedules->count() !== $expectedCount) {
            throw (new ModelNotFoundException())
                ->setModel(Schedule::getProjectClassName(), $scheduleIds);
        }
    }

    protected function manageSchedules(Collection $schedules): Collection
    {
        return $schedules->map(
            fn (Schedule $schedule): Schedule => $this
                ->manageLockedSchedule($schedule)
        );
    }

    protected function manageLockedSchedule(Schedule $schedule): Schedule
    {
        if ($schedule->isManaged()) {
            return $schedule;
        }

        $managedAt = now();

        $this->markScheduleManaged($schedule, $managedAt);
        $this->markNotificationsManaged($schedule, $managedAt);

        return $schedule;
    }

    protected function markScheduleManaged(
        Schedule $schedule,
        CarbonInterface $managedAt
    ): void {
        $schedule->markManaged($managedAt);
    }

    protected function markNotificationsManaged(
        Schedule $schedule,
        CarbonInterface $managedAt
    ): void {
        $schedule
            ->scheduledNotifications()
            ->whereNull('managed_at')
            ->update(['managed_at' => $managedAt]);
    }
}
