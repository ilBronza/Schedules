<?php

namespace IlBronza\Schedules\Helpers\Calculators;

use IlBronza\Schedules\Models\Schedule;

/**
 * Compares schedule values in measurement-unit space.
 *
 * hasReached ≡ calculateDifference(threshold, current) >= 0
 * (current is at or past the threshold in the unit's ordering).
 *
 * Equality counts as reached. No float epsilon is applied: comparison uses
 * the raw helper result (IEEE-754 for numeric units). Dates and timezones
 * are entirely delegated to the measurement unit helper.
 */
class ScheduleValueComparator
{
    public function hasReached(
        Schedule $schedule,
        mixed $currentValue,
        mixed $thresholdValue
    ): bool {
        $difference = $schedule
            ->getMeasurementUnitHelper()
            ->calculateDifference($thresholdValue, $currentValue);

        return $difference >= 0;
    }
}
