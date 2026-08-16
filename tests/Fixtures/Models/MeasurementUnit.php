<?php

namespace IlBronza\Schedules\Tests\Fixtures\Models;

use IlBronza\MeasurementUnits\Models\MeasurementUnit as BaseMeasurementUnit;

class MeasurementUnit extends BaseMeasurementUnit
{
    public $incrementing = false;

    protected $keyType = 'string';
}
