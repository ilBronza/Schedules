<?php

namespace IlBronza\Schedules\Helpers\Applicators;

use IlBronza\Schedules\Models\Schedule;

class ScheduleStartingCalculatorHelper extends ScheduleLimitsCalculatorHelper
{
    public function calculateScheduleLimit() : mixed
    {
        $measurementUnitHelper = $this->getMeasurementUnitHelper();

        return $measurementUnitHelper->remove(
            $this->getLimitValue(),
            $this->getValidity()
        );
    }

    public function extractLimitValue(Schedule $schedule) : mixed
    {
        return $schedule->getDeadlineValue();
    }
}