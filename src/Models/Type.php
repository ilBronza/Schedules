<?php

namespace IlBronza\Schedules\Models;

use IlBronza\Schedules\Models\MeasurementUnit;

class Type extends SchedulePackageBaseModel
{
    public function measurementUnit()
    {
    	return $this->belongsTo(MeasurementUnit::getProjectClassName());
    }
}