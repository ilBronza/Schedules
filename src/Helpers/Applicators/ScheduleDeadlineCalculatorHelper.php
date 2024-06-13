<?php

namespace IlBronza\Schedules\Helpers\Applicators;

use IlBronza\Schedules\Models\Schedule;

class ScheduleDeadlineCalculatorHelper extends ScheduleLimitsCalculatorHelper
{
    public function calculateScheduleLimit() : mixed
    {
        $measurementUnitHelper = $this->getMeasurementUnitHelper();

        return $measurementUnitHelper->add(
            $this->getLimitValue(),
            $this->getValidity()
        );
    }

    public function extractLimitValue(Schedule $schedule) : mixed
    {
        return $schedule->getStartingValue();
    }
}