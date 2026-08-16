<?php

namespace IlBronza\Schedules\Models;

use IlBronza\MeasurementUnits\Models\MeasurementUnit;
use IlBronza\Schedules\Models\SchedulePackageBaseModel;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Services\ScheduleSoftDeleteLifecycle;
use RuntimeException;

class TypeNotification extends SchedulePackageBaseModel
{
    static $deletingRelationships = [];

    protected static function booted(): void
    {
        static::saving(function (TypeNotification $typeNotification): void {
            $typeNotification->assertTypeIdIsImmutable();
            $typeNotification->assertRepetitionConfigurationIsValid();
        });

        static::deleting(function (TypeNotification $typeNotification): void {
            app(ScheduleSoftDeleteLifecycle::class)
                ->assertTypeNotificationCanBeDeleted($typeNotification);
        });
    }

    public function assertTypeIdIsImmutable(): void
    {
        if (! $this->exists) {
            return;
        }

        if (! $this->isDirty('type_id')) {
            return;
        }

        throw new RuntimeException(
            'TypeNotification ['.$this->getKey().'] type_id cannot be changed after creation.'
        );
    }

    public function type()
    {
        return $this->belongsTo(Type::getProjectClassName());
    }

    public function scheduledNotifications()
    {
        return $this->hasMany(
            ScheduledNotification::getProjectClassName(),
            'type_notification_id'
        );
    }

    public function repeatingMeasurementUnit()
    {
        return $this->belongsTo(
            MeasurementUnit::getProjectClassName(),
            'repeat_every_measurement_unit_id'
        );
    }

    public function getBefore(): mixed
    {
        if (is_null($this->before)) {
            throw new RuntimeException(
                'TypeNotification ['.$this->getKey().'] is missing required before value.'
            );
        }

        return $this->before;
    }

    public function getFirstRepetition(): mixed
    {
        return $this->getBefore();
    }

    public function getLastRepetition(): mixed
    {
        return $this->last_repetition;
    }

    public function getUrgency(): ?int
    {
        if (is_null($this->urgency)) {
            return null;
        }

        if (! is_numeric($this->urgency) || (int) $this->urgency < 0) {
            throw new RuntimeException(
                'TypeNotification ['.$this->getKey().'] has invalid urgency value.'
            );
        }

        return (int) $this->urgency;
    }

    public function isRepeating(): bool
    {
        $this->assertRepetitionConfigurationIsValid();

        return ! is_null($this->repeat_every);
    }

    public function getRepeatEvery(): mixed
    {
        $this->assertRepetitionConfigurationIsValid();

        return $this->repeat_every;
    }

    public function getRepeatingMeasurementUnit(): MeasurementUnit
    {
        $this->assertRepetitionConfigurationIsValid();

        $measurementUnit = $this->repeatingMeasurementUnit;

        if (is_null($measurementUnit)) {
            throw new RuntimeException(
                'TypeNotification ['.$this->getKey().'] is missing required repeatingMeasurementUnit relation.'
            );
        }

        return $measurementUnit;
    }

    public function assertRepetitionConfigurationIsValid(): void
    {
        $id = $this->getKey();
        $hasRepeatEvery = ! is_null($this->repeat_every);
        $hasRepeatUnit = ! is_null($this->repeat_every_measurement_unit_id);
        $hasLastRepetition = ! is_null($this->last_repetition);

        if ($hasRepeatEvery xor $hasRepeatUnit) {
            throw new RuntimeException(
                'TypeNotification ['.$id.'] requires both repeat_every and repeat_every_measurement_unit_id.'
            );
        }

        if ($hasLastRepetition && ! $hasRepeatEvery) {
            throw new RuntimeException(
                'TypeNotification ['.$id.'] cannot define last_repetition without repetition (repeat_every, repeat_every_measurement_unit_id).'
            );
        }

        if (
            $hasRepeatEvery
            && (! is_numeric($this->repeat_every) || (float) $this->repeat_every <= 0)
        ) {
            throw new RuntimeException(
                'TypeNotification ['.$id.'] has invalid repeat_every value.'
            );
        }

        if (! $hasLastRepetition) {
            return;
        }

        if (! is_numeric($this->last_repetition) || (float) $this->last_repetition < 0) {
            throw new RuntimeException(
                'TypeNotification ['.$id.'] has invalid last_repetition value.'
            );
        }

        if (
            is_numeric($this->before)
            && (float) $this->last_repetition > (float) $this->before
        ) {
            throw new RuntimeException(
                'TypeNotification ['.$id.'] last_repetition must be closer to the deadline than before (first repetition).'
            );
        }
    }

    public function getName() : ? string
    {
        if (is_null($this->before)) {
            return null;
        }

        return (string) $this->getBefore();
    }

    public function measurementUnit()
    {
        return $this->hasOneThrough(
            MeasurementUnit::getProjectClassName(),
            Type::getProjectClassName(),
            'id', // refers to id column on type table
            'id', // refers to id column on measurementUnit table
            'type_id',
            'measurement_unit_id' // refers to measurement_unit_id column on types table
        );
    }

    public function getTranslatedName()
    {
        return $this->getName();
    }
}
