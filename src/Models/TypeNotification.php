<?php

namespace IlBronza\Schedules\Models;

use IlBronza\MeasurementUnits\Models\MeasurementUnit;
use IlBronza\Schedules\Models\SchedulePackageBaseModel;
use IlBronza\Schedules\Models\Type;

class TypeNotification extends SchedulePackageBaseModel
{
    static $deletingRelationships = [];

    public function type()
    {
        return $this->belongsTo(Type::class);
    }

    public function repeatingMeasurementUnit()
    {
        return $this->belongsTo(MeasurementUnit::class, 'repeat_every_measurement_unit_id');
    }

    public function getBefore()
    {
        return $this->before;
    }

    public function getName() : ? string
    {
        return $this->getBefore();
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