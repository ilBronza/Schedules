<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\AccountManager\AccountManagerServiceProvider;
use IlBronza\Buttons\ButtonsServiceProvider;
use IlBronza\CRUD\CRUDServiceProvider;
use IlBronza\Datatables\DatatablesServiceProvider;
use IlBronza\Form\FormServiceProvider;
use IlBronza\FormField\FormFieldServiceProvider;
use IlBronza\Menu\MenuServiceProvider;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeEditUpdateFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Types\TypeCrudController;
use IlBronza\Schedules\Http\Controllers\Types\TypeEditUpdateController;
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

class TypeEditUpdateControllerTest extends TestCase
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

    public function testEditAndUpdateAreBoundToTypeEditUpdateController(): void
    {
        $this->assertSame(
            TypeEditUpdateController::class,
            config('schedules.models.type.controllers.edit')
        );
        $this->assertSame(
            TypeEditUpdateController::class,
            config('schedules.models.type.controllers.update')
        );
        $this->assertNotSame(
            TypeCrudController::class,
            config('schedules.models.type.controllers.edit')
        );
        $this->assertFalse(
            in_array('edit', (new TypeCrudController())->allowedMethods, true)
        );
        $this->assertFalse(
            in_array('update', (new TypeCrudController())->allowedMethods, true)
        );
    }

    public function testPutRouteUsesTheConfiguredUpdateController(): void
    {
        $updateRoute = Route::getRoutes()->getByName('ibSchedules.types.update');
        $editRoute = Route::getRoutes()->getByName('ibSchedules.types.edit');

        $this->assertSame(
            config('schedules.models.type.controllers.update'),
            $updateRoute->getControllerClass()
        );
        $this->assertSame(
            TypeEditUpdateController::class,
            $updateRoute->getControllerClass()
        );
        $this->assertSame('update', $updateRoute->getActionMethod());
        $this->assertSame(
            TypeEditUpdateController::class,
            $editRoute->getControllerClass()
        );
        $this->assertSame('edit', $editRoute->getActionMethod());
        $this->assertContains('PUT', $updateRoute->methods());
        $this->assertContains('GET', $editRoute->methods());
    }

    public function testEditFieldsetExposesSourceAndMethod(): void
    {
        $this->assertSame(
            TypeEditUpdateFieldsetsParameters::class,
            config('schedules.models.type.parametersFiles.edit')
        );

        $fields = (new TypeEditUpdateFieldsetsParameters())
            ->_getFieldsetsParameters()['applications']['fields']['models']['fields'];

        $this->assertArrayHasKey('source', $fields);
        $this->assertArrayHasKey('method', $fields);
        $this->assertSame(
            [
                'type' => 'text',
                'rules' => 'string|nullable|max:255',
            ],
            $fields['source']
        );
        $this->assertSame(
            [
                'type' => 'text',
                'rules' => 'string|nullable|max:255',
            ],
            $fields['method']
        );
    }

    public function testGetEditCompletesAndExposesSourceAndMethod(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->withoutExceptionHandling()
            ->get(route('ibSchedules.types.edit', $type));

        $response->assertOk();
        $response->assertSee('source', false);
        $response->assertSee('method', false);
    }

    public function testPutUpdateSavesSourceAndMethod(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->withoutExceptionHandling()
            ->put(route('ibSchedules.types.update', $type), [
                'name' => 'Maintenance',
                'validity' => 100,
                'percentage_validity' => 99,
                'allow_multiple' => 0,
                'measurement_unit_id' => $type->measurement_unit_id,
                'models' => [
                    [
                        'model' => Schedulable::class,
                        'source' => 'alternate_reading',
                        'method' => 'getAlternateReading',
                    ],
                ],
                'roles' => [],
            ]);

        $response->assertRedirect();

        $saved = $type->fresh()->models;

        $this->assertSame(Schedulable::class, $saved[0]['model']);
        $this->assertSame('alternate_reading', $saved[0]['source']);
        $this->assertSame('getAlternateReading', $saved[0]['method']);
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
