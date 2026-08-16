<?php

namespace IlBronza\Schedules\Services;

use IlBronza\MeasurementUnits\Models\MeasurementUnit;
use IlBronza\Schedules\Contracts\Schedulable;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\ScheduledNotificationDispatch;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Models\TypeNotification;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class SchedulesConfigurationValidator
{
    public function __construct(
        protected ScheduleNotificationRoleModelResolver $roleModelResolver
    ) {
    }

    public function validate(): void
    {
        $this->validatePackageModels();
        $this->validateMeasurementUnitModel();
        $this->validateApplicableModels();
        $this->validateNotificationRoleModel();
    }

    protected function validatePackageModels(): void
    {
        $this->validateModel('schedule', Schedule::class);
        $this->validateModel(
            'scheduledNotification',
            ScheduledNotification::class
        );
        $this->validateModel(
            'scheduledNotificationDispatch',
            ScheduledNotificationDispatch::class
        );
        $this->validateModel('type', Type::class);
        $this->validateModel('typeNotification', TypeNotification::class);
    }

    protected function validateModel(
        string $configKey,
        string $expectedClass
    ): void {
        $modelClass = config("schedules.models.{$configKey}.class");

        $this->ensureModelExtends(
            $modelClass,
            $expectedClass,
            "schedules.models.{$configKey}.class"
        );
    }

    protected function validateMeasurementUnitModel(): void
    {
        $modelClass = config(
            'measurementunits.models.measurementUnit.class'
        );

        $this->ensureModelExtends(
            $modelClass,
            MeasurementUnit::class,
            'measurementunits.models.measurementUnit.class'
        );
    }

    protected function validateApplicableModels(): void
    {
        foreach (config('schedules.applicableTo', []) as $modelClass => $label) {
            $this->ensureModelExtends(
                $modelClass,
                Model::class,
                'schedules.applicableTo'
            );

            if (! is_a($modelClass, Schedulable::class, true)) {
                throw new RuntimeException(
                    "schedules.applicableTo model {$modelClass} must implement "
                    . Schedulable::class
                );
            }

            if (! is_string($label) || $label === '') {
                throw new RuntimeException(
                    "The applicable schedule model {$modelClass} needs a label"
                );
            }
        }
    }

    protected function validateNotificationRoleModel(): void
    {
        $roleModelClass = $this->roleModelResolver->configuredClass();

        if (is_null($roleModelClass)) {
            return;
        }

        $this->roleModelResolver->ensureIsValid($roleModelClass);
    }

    protected function ensureModelExtends(
        mixed $modelClass,
        string $expectedClass,
        string $configKey
    ): void {
        if (! is_string($modelClass)
            || ! class_exists($modelClass)
            || ! is_a($modelClass, $expectedClass, true)) {
            throw new RuntimeException(
                "{$configKey} must be a {$expectedClass} model class"
            );
        }
    }
}
