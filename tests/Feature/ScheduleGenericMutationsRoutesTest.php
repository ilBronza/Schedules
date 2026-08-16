<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\Buttons\Button;
use IlBronza\Buttons\ButtonsServiceProvider;
use IlBronza\CRUD\Providers\RouterProvider\IbRouter;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\ScheduleIndexFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Providers\FieldsGroups\ScheduleRelatedFieldsGroupParametersFile;
use IlBronza\Schedules\Http\Controllers\Schedules\ScheduleCrudController;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class ScheduleGenericMutationsRoutesTest extends TestCase
{
    use CreatesScheduleFixtures;

    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            ButtonsServiceProvider::class,
        ]);
    }

    public function testGenericMutationRoutesAreNotRegistered(): void
    {
        foreach ([
            'ibSchedules.schedules.create',
            'ibSchedules.schedules.store',
            'ibSchedules.schedules.edit',
            'ibSchedules.schedules.update',
            'ibSchedules.schedules.destroy',
        ] as $routeName) {
            $this->assertFalse(
                Route::has($routeName),
                "Generic Schedule mutation route [{$routeName}] must not exist"
            );
        }
    }

    public function testDomainAwareScheduleRoutesRemainRegistered(): void
    {
        foreach ([
            'ibSchedules.schedules.index',
            'ibSchedules.schedules.show',
            'ibSchedules.schedules.manage',
            'ibSchedules.schedules.manageMany',
        ] as $routeName) {
            $this->assertTrue(
                Route::has($routeName),
                "Schedule route [{$routeName}] must remain registered"
            );
        }
    }

    public function testScheduleControllerAllowsOnlyIndexShowAndManage(): void
    {
        $controller = new ScheduleCrudController();

        $this->assertSame(
            ['index', 'show', 'manage', 'manageMany'],
            $controller->allowedMethods
        );
        $this->assertTrue($controller->avoidCreateButton);
    }

    public function testScheduleConfigDoesNotExposeGenericMutationControllers(): void
    {
        $controllers = config('schedules.models.schedule.controllers');

        foreach (['create', 'store', 'edit', 'update', 'destroy'] as $action) {
            $this->assertArrayNotHasKey(
                $action,
                $controllers,
                "schedules.models.schedule.controllers.{$action} must be removed"
            );
        }

        foreach (['index', 'show', 'manage', 'manageMany'] as $action) {
            $this->assertSame(
                ScheduleCrudController::class,
                $controllers[$action]
            );
        }
    }

    public function testForbiddenMutationsReturnNotFoundOrMethodNotAllowed(): void
    {
        $schedule = $this->createSchedule();
        $prefix = '/schedules-management/schedules';

        $this
            ->withoutMiddleware()
            ->post($prefix, [])
            ->assertMethodNotAllowed();

        $this
            ->withoutMiddleware()
            ->get("{$prefix}/{$schedule->getKey()}/edit")
            ->assertNotFound();

        $this
            ->withoutMiddleware()
            ->put("{$prefix}/{$schedule->getKey()}", [])
            ->assertMethodNotAllowed();

        $this
            ->withoutMiddleware()
            ->delete("{$prefix}/{$schedule->getKey()}/delete")
            ->assertNotFound();
    }

    public function testIndexAndRelatedButtonsDoNotTargetRemovedMutationRoutes(): void
    {
        $indexFields = ScheduleIndexFieldsGroupParametersFile::getFieldsGroup()['fields'];
        $relatedFields = ScheduleRelatedFieldsGroupParametersFile::getFieldsGroup()['fields'];

        foreach ([$indexFields, $relatedFields] as $fields) {
            $this->assertArrayNotHasKey('mySelfEdit', $fields);
            $this->assertArrayNotHasKey('mySelfDelete', $fields);
            $this->assertArrayHasKey('mySelfSee', $fields);
        }

        $manageHref = Button::create([
            'href' => IbRouter::route(
                app('schedules'),
                'schedules.manageMany'
            ),
            'text' => 'schedules::schedules.manageSelected',
            'icon' => 'check',
        ])->getHref();

        $this->assertStringContainsString(
            route('ibSchedules.schedules.manageMany'),
            $manageHref
        );
        $this->assertStringNotContainsString('/create', $manageHref);
        $this->assertStringNotContainsString('/edit', $manageHref);
        $this->assertStringNotContainsString('/delete', $manageHref);
    }

    protected function createSchedule(): Schedule
    {
        $type = $this->createType($this->createMeasurementUnit());
        $schedulable = $this->createSchedulable();

        return Schedule::query()->forceCreate([
            'type_id' => $type->getKey(),
            'schedulable_type' => $schedulable->getMorphClass(),
            'schedulable_id' => $schedulable->getKey(),
            'starting_value' => 0,
            'deadline_value' => 100,
        ]);
    }
}
