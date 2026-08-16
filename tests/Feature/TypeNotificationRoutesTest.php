<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\AccountManager\AccountManagerServiceProvider;
use IlBronza\Buttons\ButtonsServiceProvider;
use IlBronza\CRUD\CRUDServiceProvider;
use IlBronza\Datatables\DatatablesServiceProvider;
use IlBronza\Form\FormServiceProvider;
use IlBronza\FormField\FormFieldServiceProvider;
use IlBronza\Menu\MenuServiceProvider;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeNotificationEditUpdateFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\TypeNotifications\TypeNotificationCrudController;
use IlBronza\Schedules\Models\TypeNotification;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\Fixtures\Models\ActingSuperadmin;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use IlBronza\Schedules\Tests\TestCase;
use IlBronza\UikitTemplate\UikitTemplate;
use IlBronza\UikitTemplate\UikitTemplateServiceProvider;
use IlBronza\Ukn\UknServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionServiceProvider;

class TypeNotificationRoutesTest extends TestCase
{
    use CreatesScheduleFixtures;

    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            PermissionServiceProvider::class,
            AccountManagerServiceProvider::class,
            UknServiceProvider::class,
            MenuServiceProvider::class,
            UikitTemplateServiceProvider::class,
            ButtonsServiceProvider::class,
            FormFieldServiceProvider::class,
            FormServiceProvider::class,
            DatatablesServiceProvider::class,
            CRUDServiceProvider::class,
        ]);
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.template', UikitTemplate::class);
        $app['config']->set('menu.childrenPerColumn', 8);
        $app['config']->set('menu.serviceProviders', []);
        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => ActingSuperadmin::class,
        ]);
        $app['config']->set('schedules.applicableTo', [
            Schedulable::class => 'schedulables',
        ]);

        $compiledViews = sys_get_temp_dir() . '/schedules-tests-views';

        if (! is_dir($compiledViews)) {
            mkdir($compiledViews, 0777, true);
        }

        $app['config']->set('view.compiled', $compiledViews);
    }

    protected function defineRoutes($router): void
    {
        $router->get('login', fn () => response('login'))->name('login');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('datatabledata', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('key');
            $table->json('data');
            $table->timestamps();
        });
    }

    public function testGlobalTypeNotificationCreateRouteDoesNotExist(): void
    {
        $this->assertFalse(
            Route::has('ibSchedules.typeNotifications.create'),
            'Global typeNotifications.create must not exist: create always requires a Type'
        );
    }

    public function testNestedTypeNotificationCreateRouteExists(): void
    {
        $this->assertTrue(
            Route::has('ibSchedules.types.typeNotifications.create')
        );
        $this->assertTrue(
            Route::has('ibSchedules.types.typeNotifications.store')
        );
    }

    public function testCreateButtonsDoNotTargetRemovedGlobalCreateRoute(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $buttonHref = $type->getCreateNotificationTypeButton()->getHref();

        $this->assertStringContainsString(
            route('ibSchedules.types.typeNotifications.create', ['type' => $type]),
            $buttonHref
        );
        $this->assertStringNotContainsString(
            '/type-notifications/create',
            $buttonHref
        );

        $controller = new TypeNotificationCrudController();

        $this->assertTrue($controller->avoidCreateButton);
    }

    public function testFieldsetRepetitionRulesRequirePairedFieldsAndLastRepetitionWithinBefore(): void
    {
        $fields = (new TypeNotificationEditUpdateFieldsetsParameters())
            ->_getFieldsetsParameters()['repetition']['fields'];

        $this->assertStringContainsString(
            'required_with:repeat_every_measurement_unit_id,last_repetition',
            $fields['repeat_every']['number']
        );
        $this->assertStringContainsString(
            'lte:before',
            $fields['last_repetition']['number']
        );
        $this->assertStringContainsString(
            'required_with:repeat_every',
            $fields['repeat_every_measurement_unit_id']['rules']
        );
    }

    public function testStoreCreatesNonRepeatingTypeNotification(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.typeNotifications.store', ['type' => $type]),
                [
                    'before' => 15,
                    'urgency' => 1,
                ]
            );

        $response->assertRedirect();

        $created = TypeNotification::query()->where('before', 15)->first();

        $this->assertNotNull($created);
        $this->assertNull($created->repeat_every);
        $this->assertNull($created->repeat_every_measurement_unit_id);
        $this->assertNull($created->last_repetition);
    }

    public function testStoreRejectsRepeatEveryWithoutMeasurementUnit(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.typeNotifications.store', ['type' => $type]),
                [
                    'before' => 20,
                    'repeat_every' => 10,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['repeat_every_measurement_unit_id']);

        $this->assertSame(0, TypeNotification::query()->count());
    }

    public function testStoreRejectsMeasurementUnitWithoutRepeatEvery(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit);

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.typeNotifications.store', ['type' => $type]),
                [
                    'before' => 20,
                    'repeat_every_measurement_unit_id' => $measurementUnit->getKey(),
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['repeat_every']);

        $this->assertSame(0, TypeNotification::query()->count());
    }

    public function testStoreRejectsLastRepetitionWithoutRepetition(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.typeNotifications.store', ['type' => $type]),
                [
                    'before' => 20,
                    'last_repetition' => 5,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['repeat_every']);

        $this->assertSame(0, TypeNotification::query()->count());
    }

    public function testStoreRejectsLastRepetitionFartherThanBefore(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit);

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.typeNotifications.store', ['type' => $type]),
                [
                    'before' => 20,
                    'last_repetition' => 30,
                    'repeat_every' => 5,
                    'repeat_every_measurement_unit_id' => $measurementUnit->getKey(),
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['last_repetition']);

        $this->assertSame(0, TypeNotification::query()->count());
    }

    public function testStoreRejectsNonPositiveRepeatEvery(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit);

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.typeNotifications.store', ['type' => $type]),
                [
                    'before' => 20,
                    'repeat_every' => 0,
                    'repeat_every_measurement_unit_id' => $measurementUnit->getKey(),
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['repeat_every']);

        $this->assertSame(0, TypeNotification::query()->count());
    }

    public function testUpdateRejectsInvalidRepetitionConfiguration(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit);
        $typeNotification = $this->createTypeNotification($type, before: 20);

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->putJson(
                route('ibSchedules.typeNotifications.update', $typeNotification),
                [
                    'before' => 25,
                    'last_repetition' => 10,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['repeat_every']);

        $fresh = $typeNotification->fresh();

        $this->assertSame('20', (string) $fresh->before);
        $this->assertNull($fresh->last_repetition);
        $this->assertNull($fresh->repeat_every);
    }

    public function testUpdateAcceptsValidRepeatingConfiguration(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit);
        $typeNotification = $this->createTypeNotification($type, before: 20);

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->putJson(
                route('ibSchedules.typeNotifications.update', $typeNotification),
                [
                    'before' => 40,
                    'last_repetition' => 10,
                    'repeat_every' => 10,
                    'repeat_every_measurement_unit_id' => $measurementUnit->getKey(),
                    'urgency' => 3,
                ]
            );

        $response->assertRedirect();

        $fresh = $typeNotification->fresh();

        $this->assertSame('40', (string) $fresh->before);
        $this->assertSame('10', (string) $fresh->last_repetition);
        $this->assertSame('10', (string) $fresh->repeat_every);
        $this->assertSame(
            (string) $measurementUnit->getKey(),
            (string) $fresh->repeat_every_measurement_unit_id
        );
    }

    public function testUpdateRejectsTypeIdChange(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $originalType = $this->createType($measurementUnit);
        $otherType = $this->createType($measurementUnit, [
            'id' => 'inspection',
            'name' => 'Inspection',
        ]);
        $typeNotification = $this->createTypeNotification($originalType, before: 20);

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->putJson(
                route('ibSchedules.typeNotifications.update', $typeNotification),
                [
                    'before' => 25,
                    'type_id' => $otherType->getKey(),
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type_id']);

        $fresh = $typeNotification->fresh();

        $this->assertSame((string) $originalType->getKey(), (string) $fresh->type_id);
        $this->assertSame('20', (string) $fresh->before);
    }

    protected function superadmin(): ActingSuperadmin
    {
        $user = new ActingSuperadmin();
        $user->forceFill([
            'id' => 1,
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);

        return $user;
    }
}
