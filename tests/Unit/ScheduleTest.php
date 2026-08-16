<?php

namespace IlBronza\Schedules\Tests\Unit;

use Carbon\Carbon;
use Exception;
use IlBronza\MeasurementUnits\BaseMeasurementUnitHelpers\BaseMeasurementUnitHelper;
use IlBronza\MeasurementUnits\BaseMeasurementUnitHelpers\Day;
use IlBronza\Schedules\Helpers\Calculators\ScheduleValueComparator;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mathematical semantics of Schedule::getPercentageValidityAttribute():
 *
 * percentage = (delta / span) * 100
 *   span  = difference(starting, deadline)
 *   delta = difference(starting, current)
 *
 * - deadline reached (comparator) → 100
 * - current == starting → 0
 * - current before starting → negative
 * - zero span (starting == deadline) while not yet reached → RuntimeException
 * - floats: raw IEEE-754, no epsilon
 * - dates/timezones: delegated to the measurement unit helper
 */
class ScheduleTest extends TestCase
{
    use CreatesScheduleFixtures;

    public function testStartingEqualToDeadlineThrowsWhenNotYetReached(): void
    {
        $schedule = $this->schedule(
            starting: 100,
            deadline: 100,
            current: 50
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('zero span between starting and deadline');

        $schedule->getPercentageValidityAttribute();
    }

    public function testZeroValidityWhenCurrentEqualsStarting(): void
    {
        $this->assertSame(
            0.0,
            $this->schedule(starting: 10, deadline: 110, current: 10)
                ->getPercentageValidityAttribute()
        );
    }

    public function testNegativeValuesComputePercentage(): void
    {
        $this->assertSame(
            50.0,
            $this->schedule(starting: -100, deadline: 0, current: -50)
                ->getPercentageValidityAttribute()
        );
    }

    public function testCurrentBeforeStartingYieldsNegativePercentage(): void
    {
        $this->assertSame(
            -25.0,
            $this->schedule(starting: 100, deadline: 200, current: 75)
                ->getPercentageValidityAttribute()
        );
    }

    public function testBeyondDeadlineReturnsOneHundred(): void
    {
        $this->assertSame(
            100.0,
            $this->schedule(starting: 0, deadline: 100, current: 150)
                ->getPercentageValidityAttribute()
        );
    }

    public function testDateTimezonePercentageUsesMeasurementUnitDifference(): void
    {
        $starting = Carbon::parse('2026-01-01 00:00:00', 'UTC');
        $deadline = Carbon::parse('2026-01-11 00:00:00', 'UTC');
        // Same absolute instant as 2026-01-06 00:00 UTC.
        $current = Carbon::parse('2026-01-06 01:00:00', 'Europe/Rome');

        $this->assertSame(
            50.0,
            $this->schedule(
                starting: $starting,
                deadline: $deadline,
                current: $current,
                helper: new Day()
            )->getPercentageValidityAttribute()
        );
    }

    public function testFloatInexactPrecisionDoesNotDivideByZeroOnNearEqualSpan(): void
    {
        // 0.1 + 0.2 is not exactly 0.3 in IEEE-754.
        $starting = 0.0;
        $deadline = 0.1 + 0.2;
        $current = 0.15;

        $percentage = $this->schedule(
            starting: $starting,
            deadline: $deadline,
            current: $current
        )->getPercentageValidityAttribute();

        $this->assertEqualsWithDelta(50.0, $percentage, 0.0001);
    }

    public function testMidSpanPercentageIsFifty(): void
    {
        $this->assertSame(
            50.0,
            $this->schedule(starting: 0, deadline: 100, current: 50)
                ->getPercentageValidityAttribute()
        );
    }

    public function testReadingPercentageWithNullStartingDoesNotWriteToDatabase(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $schedulable = $this->createSchedulable(currentValue: 50);
        $frozenUpdatedAt = Carbon::parse('2026-03-01 10:00:00');

        $schedule = Schedule::query()->forceCreate([
            'type_id' => $type->getKey(),
            'schedulable_type' => $schedulable->getMorphClass(),
            'schedulable_id' => $schedulable->getKey(),
            'starting_value' => null,
            'deadline_value' => 100,
            'created_at' => $frozenUpdatedAt,
            'updated_at' => $frozenUpdatedAt,
        ]);
        $schedule->setRelation('type', $type);
        $schedule->setRelation('schedulable', $schedulable);

        $updateQueries = [];
        DB::listen(function ($query) use (&$updateQueries): void {
            if (preg_match('/^\s*update\b/i', $query->sql) === 1) {
                $updateQueries[] = $query->sql;
            }
        });

        $percentage = $schedule->getPercentageValidity();

        $this->assertSame(50.0, $percentage);
        $this->assertSame([], $updateQueries);
        $this->assertNull($schedule->getAttributes()['starting_value'] ?? null);
        $this->assertFalse($schedule->isDirty());

        $fresh = $schedule->fresh();
        $this->assertNull($fresh->starting_value);
        $this->assertTrue(
            $frozenUpdatedAt->eq($fresh->updated_at),
            'Reading percentage must not bump updated_at.'
        );
    }

    public function testReadingPercentageWithInvalidStartingPropagatesMeasurementUnitException(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $schedulable = $this->createSchedulable(currentValue: 50);

        $schedule = Schedule::query()->forceCreate([
            'type_id' => $type->getKey(),
            'schedulable_type' => $schedulable->getMorphClass(),
            'schedulable_id' => $schedulable->getKey(),
            'starting_value' => 'not-a-number',
            'deadline_value' => 100,
        ]);
        $schedule->setRelation('type', $type);
        $schedule->setRelation('schedulable', $schedulable);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(9901);
        $this->expectExceptionMessageMatches('/not-a-number/');

        $schedule->getPercentageValidity();
    }

    protected function schedule(
        mixed $starting,
        mixed $deadline,
        mixed $current,
        ?BaseMeasurementUnitHelper $helper = null
    ): Schedule {
        $helper ??= $this->numericHelper();

        $schedule = $this->getMockBuilder(Schedule::class)
            ->onlyMethods([
                'getDeadlineValue',
                'getCurrentValue',
                'getStartingValue',
                'getMeasurementUnitHelper',
                'getValueComparator',
            ])
            ->getMock();

        // Raw attribute drives the null vs parse branch; getters stay mocked.
        $schedule->setRawAttributes([
            'id' => 'unit-schedule',
            'starting_value' => is_null($starting) ? null : 'set',
        ], true);

        $schedule->method('getDeadlineValue')->willReturn($deadline);
        $schedule->method('getCurrentValue')->willReturn($current);
        $schedule->method('getStartingValue')->willReturn($starting);
        $schedule->method('getMeasurementUnitHelper')->willReturn($helper);
        $schedule->method('getValueComparator')->willReturn(
            new ScheduleValueComparator()
        );

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
