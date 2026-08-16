<?php

namespace IlBronza\Schedules\Tests\Concerns;

use IlBronza\MeasurementUnits\Models\MeasurementUnit;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Models\TypeNotification;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait CreatesScheduleFixtures
{
    protected function createMeasurementUnit(): MeasurementUnit
    {
        $measurementUnitClass = MeasurementUnit::getProjectClassName();
        $existing = $measurementUnitClass::query()->find('reading');

        if ($existing) {
            return $existing;
        }

        $measurementUnit = new $measurementUnitClass();
        $measurementUnit->forceFill([
            'id' => 'reading',
            'name' => 'Reading',
            'base_measurement_unit' => 'Forfait',
            'proportion_toward_base_measurement_unit' => 1,
        ]);
        $measurementUnit->incrementing = false;
        $measurementUnit->save();

        return $measurementUnit->fresh();
    }

    protected function createType(
        MeasurementUnit $measurementUnit,
        array $attributes = []
    ): Type {
        return Type::query()->forceCreate(array_merge([
            'id' => 'maintenance',
            'name' => 'Maintenance',
            'validity' => 100,
            'measurement_unit_id' => $measurementUnit->getKey(),
            'allow_multiple' => false,
            'roles' => [],
            'models' => [
                [
                    'model' => Schedulable::class,
                    'source' => 'current_reading',
                ],
            ],
        ], $attributes));
    }

    protected function createSchedulable(
        float $currentValue = 90
    ): Schedulable {
        return Schedulable::query()->create([
            'name' => 'Vehicle 1',
            'current_reading' => $currentValue,
        ]);
    }

    protected function createTypeNotification(
        Type $type,
        mixed $before,
        array $attributes = []
    ): TypeNotification {
        return TypeNotification::query()->forceCreate(array_merge([
            'type_id' => $type->getKey(),
            'before' => $before,
        ], $attributes));
    }

    protected function createScheduledNotification(
        Schedule $schedule,
        float $deadline,
        ?Type $type = null,
        mixed $before = null
    ): ScheduledNotification {
        $type ??= Type::query()->findOrFail($schedule->type_id);
        $typeNotification = $this->createTypeNotification(
            $type,
            $before ?? abs((float) $schedule->deadline_value - $deadline)
        );

        return ScheduledNotification::query()->forceCreate([
            'schedule_id' => $schedule->getKey(),
            'type_notification_id' => $typeNotification->getKey(),
            'deadline_value' => $deadline,
        ]);
    }

    protected function persistTypeWithoutModelValidation(
        array $attributes = []
    ): Type {
        $measurementUnit = $this->createMeasurementUnit();
        $attributes = array_merge([
            'id' => 'maintenance',
            'name' => 'Maintenance',
            'validity' => 100,
            'measurement_unit_id' => $measurementUnit->getKey(),
            'allow_multiple' => false,
            'roles' => [],
            'models' => [
                [
                    'model' => Schedulable::class,
                    'source' => 'current_reading',
                ],
            ],
        ], $attributes);

        return Type::withoutEvents(function () use ($attributes): Type {
            $typeClass = Type::getProjectClassName();
            $type = new $typeClass();
            $type->incrementing = false;
            $type->forceFill($attributes);
            $type->save();

            return $type->fresh();
        });
    }

    protected function insertNotificationRow(string $id): string
    {
        $now = now();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'IlBronza\\Schedules\\Tests\\Fixtures\\Notification',
            'notifiable_type' => Schedulable::class,
            'notifiable_id' => 1,
            'data' => json_encode(['message' => 'fixture']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    protected function newNotificationId(): string
    {
        return $this->insertNotificationRow((string) Str::uuid());
    }
}
