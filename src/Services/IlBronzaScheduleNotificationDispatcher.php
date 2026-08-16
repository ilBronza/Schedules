<?php

namespace IlBronza\Schedules\Services;

use IlBronza\Notifications\ChildDatabaseNotification;
use IlBronza\Notifications\Facades\Notification;
use IlBronza\Schedules\Contracts\ScheduleNotificationDispatcher;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Notifications\ScheduleThresholdReachedNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;

class IlBronzaScheduleNotificationDispatcher implements ScheduleNotificationDispatcher
{
    public function __construct(
        protected ScheduleNotificationRoleModelResolver $roleModelResolver
    ) {
    }

    public function dispatch(
        Schedule $schedule,
        ScheduledNotification $scheduledNotification
    ): string {
        $roles = $this->resolveRoles($schedule->getType());
        $notification = $this->makeNotification($schedule, $scheduledNotification);
        $notificationId = $this->sendParentNotification($roles->shift(), $notification);

        $this->sendChildNotifications($roles, $notificationId);

        return $notificationId;
    }

    protected function resolveRoles(Type $type): EloquentCollection
    {
        $roleIds = $type->getNotificationRoleIds();

        $this->ensureRoleIdsExist($type, $roleIds);

        $rolesById = $this->getRoleModelClass()::query()
            ->whereKey($roleIds)
            ->get()
            ->keyBy(fn (Model $role) => $role->getKey());

        $this->ensureAllRolesWereFound($roleIds, $rolesById);

        return new EloquentCollection(
            $roleIds
                ->map(fn (mixed $roleId) => $rolesById->get($roleId))
                ->all()
        );
    }

    protected function ensureRoleIdsExist(Type $type, Collection $roleIds): void
    {
        if ($roleIds->isEmpty()) {
            throw new RuntimeException(
                "Schedule type {$type->getKey()} has no notification roles"
            );
        }
    }

    protected function getRoleModelClass(): string
    {
        return $this->roleModelResolver->resolve();
    }

    protected function ensureAllRolesWereFound(
        Collection $roleIds,
        EloquentCollection $roles
    ): void {
        if ($roles->count() !== $roleIds->count()) {
            throw new RuntimeException('One or more schedule notification roles do not exist');
        }
    }

    protected function makeNotification(
        Schedule $schedule,
        ScheduledNotification $scheduledNotification
    ): ScheduleThresholdReachedNotification {
        return new ScheduleThresholdReachedNotification(
            $schedule,
            $scheduledNotification
        );
    }

    protected function sendParentNotification(
        Model $role,
        ScheduleThresholdReachedNotification $notification
    ): string {
        return Notification::send($role, $notification);
    }

    protected function sendChildNotifications(
        EloquentCollection $roles,
        string $parentNotificationId
    ): void {
        foreach ($roles as $role) {
            $this->sendChildNotification($role, $parentNotificationId);
        }
    }

    protected function sendChildNotification(
        Model $role,
        string $parentNotificationId
    ): void {
        Notification::send(
            $role,
            new ChildDatabaseNotification($parentNotificationId)
        );
    }
}
