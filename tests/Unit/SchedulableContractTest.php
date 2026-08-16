<?php

namespace IlBronza\Schedules\Tests\Unit;

use IlBronza\Schedules\Contracts\Schedulable as SchedulableContract;
use IlBronza\Schedules\Helpers\Applicators\ScheduleApplicatorHelper;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Services\SchedulesConfigurationValidator;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use IlBronza\Schedules\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class SchedulableContractTest extends TestCase
{
    use CreatesScheduleFixtures;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('schedules.applicableTo', [
            Schedulable::class => 'schedulables',
        ]);
    }

    public function testTypeSaveRejectsEntryMissingSourceAndMethod(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires at least one of source or method');

        $this->createType(
            $this->createMeasurementUnit(),
            [
                'models' => [
                    [
                        'model' => Schedulable::class,
                    ],
                ],
            ]
        );
    }

    public function testTypeSaveRejectsModelNotInApplicableTo(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not configured in schedules.applicableTo');

        $this->createType(
            $this->createMeasurementUnit(),
            [
                'models' => [
                    [
                        'model' => NonSchedulableModel::class,
                        'method' => 'getCurrentReading',
                    ],
                ],
            ]
        );
    }

    public function testTypeSaveRejectsNonAllowlistedMethod(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not allowlisted');

        $this->createType(
            $this->createMeasurementUnit(),
            [
                'models' => [
                    [
                        'model' => Schedulable::class,
                        'method' => 'delete',
                    ],
                ],
            ]
        );
    }

    public function testTypeSaveRejectsNonAllowlistedSourceDerivedGetter(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not allowlisted');

        $this->createType(
            $this->createMeasurementUnit(),
            [
                'models' => [
                    [
                        'model' => Schedulable::class,
                        'source' => 'name',
                    ],
                ],
            ]
        );
    }

    public function testApplicatorRejectsDeleteMethodOnCorruptPersistedType(): void
    {
        $type = $this->persistTypeBypassingModelsValidation([
            'models' => [
                [
                    'model' => Schedulable::class,
                    'method' => 'delete',
                ],
            ],
        ]);
        $schedulable = $this->createSchedulable(currentValue: 90);

        try {
            ScheduleApplicatorHelper::applicateScheduleToModel(
                $type,
                $schedulable
            );

            $this->fail('delete must not be invoked as a schedule value getter');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'not allowlisted',
                $exception->getMessage()
            );
            $this->assertSame(1, Schedulable::query()->count());
            $this->assertSame(0, Schedule::query()->count());
        }
    }

    public function testApplicatorRejectsNonAllowlistedMethodOnCorruptPersistedType(): void
    {
        $type = $this->persistTypeBypassingModelsValidation([
            'models' => [
                [
                    'model' => Schedulable::class,
                    'method' => 'getName',
                ],
            ],
        ]);
        $schedulable = $this->createSchedulable();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not allowlisted');

        ScheduleApplicatorHelper::applicateScheduleToModel($type, $schedulable);
    }

    public function testAllowlistedMethodIsInvoked(): void
    {
        $type = $this->createType(
            $this->createMeasurementUnit(),
            [
                'models' => [
                    [
                        'model' => Schedulable::class,
                        'method' => 'getAlternateReading',
                    ],
                ],
            ]
        );
        $schedulable = $this->createSchedulable(currentValue: 90);

        $schedule = ScheduleApplicatorHelper::applicateScheduleToModel(
            $type,
            $schedulable
        );

        $this->assertSame(100.0, $schedule->getStartingValue());
    }

    public function testModelRejectsNonAllowlistedMethodDirectly(): void
    {
        $schedulable = $this->createSchedulable();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not allowlisted');

        $schedulable->getScheduleCurrentValue('delete');
    }

    public function testApplicableModelMustImplementSchedulable(): void
    {
        config([
            'schedules.applicableTo' => [
                NonSchedulableModel::class => 'non-schedulable',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(SchedulableContract::class);

        $this->app
            ->make(SchedulesConfigurationValidator::class)
            ->validate();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function persistTypeBypassingModelsValidation(array $attributes): Type
    {
        return $this->persistTypeWithoutModelValidation($attributes);
    }
}

class NonSchedulableModel extends Model
{
}
