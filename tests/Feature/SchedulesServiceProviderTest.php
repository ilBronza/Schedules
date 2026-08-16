<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\MeasurementUnits\Models\MeasurementUnit;
use IlBronza\Schedules\Http\Controllers\Schedules\ScheduleCrudController;
use IlBronza\Schedules\Models\ScheduledNotificationDispatch;
use IlBronza\Schedules\SchedulesServiceProvider;
use IlBronza\Schedules\Services\SchedulesConfigurationValidator;
use IlBronza\Schedules\Tests\Fixtures\Models\MeasurementUnit as CustomMeasurementUnit;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use IlBronza\Schedules\Tests\TestCase;
use RuntimeException;

class SchedulesServiceProviderTest extends TestCase
{
    public function testPartialProjectConfigKeepsNewPackageDefaults(): void
    {
        $this->app['config']->set('schedules', [
            'models' => [
                'schedule' => [
                    'table' => 'custom_schedules',
                ],
            ],
        ]);

        (new SchedulesServiceProvider($this->app))->register();

        $this->assertSame(
            'custom_schedules',
            config('schedules.models.schedule.table')
        );
        $this->assertSame(
            ScheduleCrudController::class,
            config('schedules.models.schedule.controllers.manageMany')
        );
        $this->assertSame(
            ScheduleCrudController::class,
            config('schedules.models.schedule.controllers.manage')
        );
        $this->assertSame([], config('schedules.applicableTo'));
        $this->assertNull(config('schedules.notifications.roleModel'));
    }

    public function testMinimalHostConfigurationIsValid(): void
    {
        config([
            'schedules.applicableTo' => [],
            'schedules.notifications.roleModel' => null,
            'accountmanager.models.role.class' => null,
        ]);

        $this->validator()->validate();

        $this->assertSame([], config('schedules.applicableTo'));
    }

    public function testInvalidApplicableModelFailsDuringValidation(): void
    {
        config([
            'schedules.applicableTo' => [
                'Missing\\Project\\Model' => 'missing',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('schedules.applicableTo');

        $this->validator()->validate();
    }

    public function testNotificationRoleMustBeNotifiable(): void
    {
        config([
            'schedules.notifications.roleModel' => Schedulable::class,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must use Laravel Notifiable');

        $this->validator()->validate();
    }

    public function testConfiguredMeasurementUnitSubclassIsAccepted(): void
    {
        config([
            'measurementunits.models.measurementUnit.class' => CustomMeasurementUnit::class,
        ]);

        $this->validator()->validate();

        $this->assertSame(
            CustomMeasurementUnit::class,
            MeasurementUnit::getProjectClassName()
        );
    }

    public function testInvalidMeasurementUnitModelFailsDuringValidation(): void
    {
        config([
            'measurementunits.models.measurementUnit.class' => \stdClass::class,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'measurementunits.models.measurementUnit.class'
        );

        $this->validator()->validate();
    }

    public function testConfiguredScheduledNotificationDispatchIsAccepted(): void
    {
        config([
            'schedules.models.scheduledNotificationDispatch.class'
                => ScheduledNotificationDispatch::class,
        ]);

        $this->validator()->validate();

        $this->assertSame(
            ScheduledNotificationDispatch::class,
            ScheduledNotificationDispatch::getProjectClassName()
        );
    }

    public function testInvalidScheduledNotificationDispatchFailsDuringValidation(): void
    {
        config([
            'schedules.models.scheduledNotificationDispatch.class'
                => \stdClass::class,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'schedules.models.scheduledNotificationDispatch.class'
        );

        $this->validator()->validate();
    }

    protected function validator(): SchedulesConfigurationValidator
    {
        return $this->app->make(SchedulesConfigurationValidator::class);
    }
}
