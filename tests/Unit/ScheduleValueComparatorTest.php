<?php

namespace IlBronza\Schedules\Tests\Unit;

use Carbon\Carbon;
use IlBronza\MeasurementUnits\BaseMeasurementUnitHelpers\BaseMeasurementUnitHelper;
use IlBronza\MeasurementUnits\BaseMeasurementUnitHelpers\Day;
use IlBronza\Schedules\Helpers\Calculators\ScheduleValueComparator;
use IlBronza\Schedules\Models\Schedule;
use PHPUnit\Framework\TestCase;

/**
 * ScheduleValueComparator semantics:
 *
 * hasReached ≡ calculateDifference(threshold, current) >= 0
 * (measurement-unit ordering: current is at or past the threshold).
 *
 * Equality counts as reached. No float epsilon is applied: comparison uses
 * the raw helper result (IEEE-754 for numeric units). Dates and timezones
 * are entirely delegated to the measurement unit helper.
 */
class ScheduleValueComparatorTest extends TestCase
{
    public function testThresholdIsReachedWhenCurrentValueIsGreater(): void
    {
        $this->assertTrue(
            $this->comparator()->hasReached($this->schedule(), 120, 100)
        );
    }

    public function testThresholdIsReachedWhenValuesAreEqual(): void
    {
        $this->assertTrue(
            $this->comparator()->hasReached($this->schedule(), 100, 100)
        );
    }

    public function testThresholdIsNotReachedWhenCurrentValueIsLower(): void
    {
        $this->assertFalse(
            $this->comparator()->hasReached($this->schedule(), 80, 100)
        );
    }

    public function testNegativeValuesRespectOrdering(): void
    {
        $this->assertTrue(
            $this->comparator()->hasReached($this->schedule(), -10, -20)
        );
        $this->assertFalse(
            $this->comparator()->hasReached($this->schedule(), -30, -20)
        );
    }

    public function testBeyondDeadlineIsReached(): void
    {
        $this->assertTrue(
            $this->comparator()->hasReached($this->schedule(), 250, 100)
        );
    }

    public function testCurrentBeforeStartingIsNotReached(): void
    {
        $this->assertFalse(
            $this->comparator()->hasReached($this->schedule(), -5, 0)
        );
    }

    public function testDateTimezoneEqualityIsReached(): void
    {
        $threshold = Carbon::parse('2026-06-01 00:00:00', 'UTC');
        // Same absolute instant as the UTC threshold.
        $current = Carbon::parse('2026-06-01 02:00:00', 'Europe/Rome');

        $this->assertTrue(
            $this->comparator()->hasReached(
                $this->schedule(new Day()),
                $current,
                $threshold
            )
        );
    }

    public function testDateTimezoneBeforeThresholdIsNotReached(): void
    {
        $threshold = Carbon::parse('2026-06-01 00:00:00', 'UTC');
        $current = Carbon::parse('2026-05-31 23:00:00', 'UTC');

        $this->assertFalse(
            $this->comparator()->hasReached(
                $this->schedule(new Day()),
                $current,
                $threshold
            )
        );
    }

    public function testFloatInexactPrecisionUsesRawIeeeComparison(): void
    {
        $sum = 0.1 + 0.2;

        // 0.1 + 0.2 > 0.3 in IEEE-754, so the threshold 0.3 is reached.
        $this->assertTrue(
            $this->comparator()->hasReached($this->schedule(), $sum, 0.3)
        );

        // Exact stored 0.3 has not reached a slightly larger float sum threshold.
        $this->assertFalse(
            $this->comparator()->hasReached($this->schedule(), 0.3, $sum)
        );
    }

    protected function comparator(): ScheduleValueComparator
    {
        return new ScheduleValueComparator();
    }

    protected function schedule(
        ?BaseMeasurementUnitHelper $helper = null
    ): Schedule {
        $schedule = $this->createMock(Schedule::class);
        $schedule
            ->method('getMeasurementUnitHelper')
            ->willReturn($helper ?? $this->numericHelper());

        return $schedule;
    }

    protected function numericHelper(): BaseMeasurementUnitHelper
    {
        $helper = $this->createMock(BaseMeasurementUnitHelper::class);
        $helper
            ->method('calculateDifference')
            ->willReturnCallback(
                fn (mixed $start, mixed $end): mixed => $end - $start
            );

        return $helper;
    }
}
