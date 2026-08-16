<?php

namespace IlBronza\Schedules\Services;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class ScheduleNotificationRoleModelResolver
{
    public function resolve(): string
    {
        $roleModelClass = $this->configuredClass();

        if (is_null($roleModelClass)) {
            throw new RuntimeException(
                'The schedule notification role model is not configured'
            );
        }

        $this->ensureIsValid($roleModelClass);

        return $roleModelClass;
    }

    public function configuredClass(): ?string
    {
        return config('schedules.notifications.roleModel')
            ?? config('accountmanager.models.role.class');
    }

    public function ensureIsValid(mixed $roleModelClass): void
    {
        if (! is_string($roleModelClass)
            || ! class_exists($roleModelClass)
            || ! is_a($roleModelClass, Model::class, true)) {
            throw new RuntimeException(
                'schedules.notifications.roleModel must be a '
                . Model::class
                . ' model class'
            );
        }

        if (! method_exists($roleModelClass, 'routeNotificationFor')) {
            throw new RuntimeException(
                'The schedule notification role model must use Laravel Notifiable'
            );
        }
    }
}
