<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\AccountManager\AccountManagerServiceProvider;
use IlBronza\Buttons\ButtonsServiceProvider;
use IlBronza\CRUD\CRUDServiceProvider;
use IlBronza\Datatables\DatatablesServiceProvider;
use IlBronza\Form\FormServiceProvider;
use IlBronza\FormField\FormFieldServiceProvider;
use IlBronza\Menu\MenuServiceProvider;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeCreateStoreFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeEditUpdateFieldsetsParameters;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\Fixtures\Models\ActingSuperadmin;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use IlBronza\Schedules\Tests\TestCase;
use IlBronza\UikitTemplate\UikitTemplate;
use IlBronza\UikitTemplate\UikitTemplateServiceProvider;
use IlBronza\Ukn\UknServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionServiceProvider;

class TypeCreateStoreFieldsetsParametersTest extends TestCase
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

    public function testCreateFieldsetExposesCurrentValueConfigurationWithStructuredRules(): void
    {
        $this->assertSame(
            TypeCreateStoreFieldsetsParameters::class,
            config('schedules.models.type.parametersFiles.create')
        );

        $parameters = (new TypeCreateStoreFieldsetsParameters())
            ->_getFieldsetsParameters();

        $base = $parameters['base']['fields'];
        $models = $parameters['applications']['fields']['models'];
        $modelFields = $models['fields'];

        $this->assertSame(
            ['boolean' => 'boolean|required'],
            $base['allow_multiple']
        );
        $this->assertStringContainsString('required', $base['measurement_unit_id']['rules']);
        $this->assertStringNotContainsString('nullable', $base['measurement_unit_id']['rules']);
        $this->assertSame(
            'array|required|min:1|type_models_configuration',
            $models['rules']
        );

        $this->assertSame('select', $modelFields['model']['type']);
        $this->assertArrayHasKey('rules', $modelFields['model']);
        $this->assertStringContainsString('required', $modelFields['model']['rules']);

        $this->assertSame('text', $modelFields['source']['type']);
        $this->assertArrayHasKey('rules', $modelFields['source']);
        $this->assertSame('string|nullable|max:255', $modelFields['source']['rules']);

        $this->assertSame('text', $modelFields['method']['type']);
        $this->assertArrayHasKey('rules', $modelFields['method']);
        $this->assertSame('string|nullable|max:255', $modelFields['method']['rules']);

        $roles = $parameters['roles']['fields']['roles']['fields']['roles'];
        $this->assertSame('select', $roles['type']);
        $this->assertArrayHasKey('rules', $roles);
    }

    public function testEditFieldsetInheritsCurrentValueConfigurationFromCreate(): void
    {
        $createFields = (new TypeCreateStoreFieldsetsParameters())
            ->_getFieldsetsParameters()['applications']['fields']['models']['fields'];
        $editFields = (new TypeEditUpdateFieldsetsParameters())
            ->_getFieldsetsParameters()['applications']['fields']['models']['fields'];

        $this->assertArrayHasKey('source', $editFields);
        $this->assertArrayHasKey('method', $editFields);
        $this->assertSame($createFields['source'], $editFields['source']);
        $this->assertSame($createFields['method'], $editFields['method']);
    }

    public function testPostStoreCreatesTypeReadyForApplicationWithoutEdit(): void
    {
        $measurementUnit = $this->createMeasurementUnit();

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->withoutExceptionHandling()
            ->post(route('ibSchedules.types.store'), [
                'name' => 'CreatedViaUi',
                'validity' => 100,
                'percentage_validity' => 99,
                'allow_multiple' => 0,
                'measurement_unit_id' => $measurementUnit->getKey(),
                'models' => [
                    [
                        'model' => Schedulable::class,
                        'source' => 'current_reading',
                        'method' => 'getCurrentReading',
                    ],
                ],
                'roles' => [],
            ]);

        $response->assertRedirect();

        $type = Type::query()->where('name', 'CreatedViaUi')->first();

        $this->assertNotNull($type);
        $this->assertSame(Schedulable::class, $type->models[0]['model']);
        $this->assertSame('current_reading', $type->models[0]['source']);
        $this->assertSame('getCurrentReading', $type->models[0]['method']);

        $schedulable = $this->createSchedulable(currentValue: 15);

        $applyResponse = $this
            ->withoutMiddleware()
            ->withoutExceptionHandling()
            ->postJson(
                route('ibSchedules.types.applicate.classname.store', [
                    'type' => $type->getKey(),
                    'alias' => 'schedulables',
                ]),
                ['ids' => [$schedulable->getKey()]]
            );

        $applyResponse
            ->assertOk()
            ->assertJson([
                'success' => true,
                'ibaction' => 'reloadTable',
            ]);

        $this->assertSame(1, Schedule::query()->count());
        $schedule = Schedule::query()->first();
        $this->assertSame(15.0, $schedule->getStartingValue());
        $this->assertSame(115.0, $schedule->getDeadlineValue());
    }

    public function testPostStoreRejectsEntryMissingSourceAndMethod(): void
    {
        $measurementUnit = $this->createMeasurementUnit();

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->postJson(route('ibSchedules.types.store'), [
                'name' => 'MissingGetter',
                'validity' => 100,
                'percentage_validity' => 99,
                'allow_multiple' => 0,
                'measurement_unit_id' => $measurementUnit->getKey(),
                'models' => [
                    [
                        'model' => Schedulable::class,
                    ],
                ],
                'roles' => [],
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['models']);
        $this->assertSame(0, Type::query()->count());
    }

    public function testPostStoreRejectsModelNotInApplicableTo(): void
    {
        $measurementUnit = $this->createMeasurementUnit();

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->postJson(route('ibSchedules.types.store'), [
                'name' => 'UnknownModel',
                'validity' => 100,
                'percentage_validity' => 99,
                'allow_multiple' => 0,
                'measurement_unit_id' => $measurementUnit->getKey(),
                'models' => [
                    [
                        'model' => \stdClass::class,
                        'method' => 'getCurrentReading',
                    ],
                ],
                'roles' => [],
            ]);

        $response->assertStatus(422);
        $this->assertSame(0, Type::query()->count());
    }

    public function testPostStoreRejectsNonAllowlistedMethod(): void
    {
        $measurementUnit = $this->createMeasurementUnit();

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->postJson(route('ibSchedules.types.store'), [
                'name' => 'BadMethod',
                'validity' => 100,
                'percentage_validity' => 99,
                'allow_multiple' => 0,
                'measurement_unit_id' => $measurementUnit->getKey(),
                'models' => [
                    [
                        'model' => Schedulable::class,
                        'method' => 'delete',
                    ],
                ],
                'roles' => [],
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['models']);
        $this->assertSame(0, Type::query()->count());
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
