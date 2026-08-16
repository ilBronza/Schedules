<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\AccountManager\AccountManagerServiceProvider;
use IlBronza\Buttons\ButtonsServiceProvider;
use IlBronza\CRUD\CRUDServiceProvider;
use IlBronza\Datatables\DatatablesServiceProvider;
use IlBronza\Form\FormServiceProvider;
use IlBronza\FormField\FormFieldServiceProvider;
use IlBronza\Menu\MenuServiceProvider;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\ScheduleShowFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeCreateStoreFieldsetsParameters;
use IlBronza\Schedules\Http\Controllers\Providers\Fieldsets\TypeShowFieldsetsParameters;
use IlBronza\Schedules\Models\Schedule;
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

class ShowRoutesTest extends TestCase
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

    public function testShowParametersFilesAreConfigured(): void
    {
        $this->assertSame(
            TypeShowFieldsetsParameters::class,
            config('schedules.models.type.parametersFiles.show')
        );
        $this->assertSame(
            ScheduleShowFieldsetsParameters::class,
            config('schedules.models.schedule.parametersFiles.show')
        );
        $this->assertNotEmpty(
            config('schedules.models.typeNotification.parametersFiles.show')
        );
        $this->assertNotSame(
            TypeCreateStoreFieldsetsParameters::class,
            config('schedules.models.type.parametersFiles.show')
        );
    }

    public function testTypeShowFieldsetDoesNotReuseCreateSelectInputs(): void
    {
        $fields = (new TypeShowFieldsetsParameters())
            ->_getFieldsetsParameters();

        $this->assertArrayHasKey('base', $fields);
        $this->assertArrayHasKey('name', $fields['base']['fields']);

        foreach ($this->collectFieldDefinitions($fields) as $field) {
            $this->assertArrayNotHasKey(
                'possibleValuesArray',
                $field,
                'Show fieldsets must not load create-style select options'
            );
        }
    }

    public function testGetTypeShowCompletes(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->withoutExceptionHandling()
            ->get(route('ibSchedules.types.show', $type));

        $response->assertOk();
        $response->assertSee('Maintenance', false);
    }

    public function testGetScheduleShowCompletes(): void
    {
        $schedule = $this->createSchedule();

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->withoutExceptionHandling()
            ->get(route('ibSchedules.schedules.show', $schedule));

        $response->assertOk();
        $response->assertSee('Maintenance', false);
    }

    public function testGetTypeNotificationShowCompletes(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $typeNotification = $this->createTypeNotification($type, 20);

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->withoutExceptionHandling()
            ->get(route('ibSchedules.typeNotifications.show', $typeNotification));

        $response->assertOk();
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

    protected function collectFieldDefinitions(array $fieldsets): array
    {
        $fields = [];

        foreach ($fieldsets as $fieldset) {
            foreach ($fieldset['fields'] ?? [] as $field) {
                $fields[] = $field;

                foreach ($field['fields'] ?? [] as $innerField) {
                    $fields[] = $innerField;
                }
            }
        }

        return $fields;
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
