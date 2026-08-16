<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\AccountManager\AccountManagerServiceProvider;
use IlBronza\Buttons\ButtonsServiceProvider;
use IlBronza\CRUD\CRUDServiceProvider;
use IlBronza\Datatables\DatatablesServiceProvider;
use IlBronza\Form\FormServiceProvider;
use IlBronza\FormField\FormFieldServiceProvider;
use IlBronza\Menu\MenuServiceProvider;
use IlBronza\Schedules\Http\Controllers\Schedules\ScheduleCrudController;
use IlBronza\Schedules\Http\Controllers\TypeNotifications\TypeNotificationCrudController;
use IlBronza\Schedules\Http\Controllers\Types\TypeApplicateClassnameController;
use IlBronza\Schedules\Http\Controllers\Types\TypeApplicateController;
use IlBronza\Schedules\Http\Controllers\Types\TypeCrudController;
use IlBronza\Schedules\Http\Controllers\Types\TypeEditUpdateController;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Models\TypeNotification;
use IlBronza\Schedules\Schedules;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\Fixtures\Models\ActingSuperadmin;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use IlBronza\Schedules\Tests\TestCase;
use IlBronza\UikitTemplate\UikitTemplate;
use IlBronza\UikitTemplate\UikitTemplateServiceProvider;
use IlBronza\Ukn\UknServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionServiceProvider;

/**
 * Smoke coverage for every public route declared in routes/schedules.php.
 *
 * Each route is checked for HTTP method, bound controller/action, middleware,
 * parameters file (when configured), happy path, denial path, and that the
 * registered controller matches the configured override key.
 */
class RouteSmokeTest extends TestCase
{
    use CreatesScheduleFixtures;

    /**
     * @return array<string, array{
     *     methods: list<string>,
     *     controller: class-string,
     *     action: string,
     *     configTarget: string,
     *     configKey: string,
     *     parametersFileConfig: ?string,
     *     fieldsGroupConfig: ?string
     * }>
     */
    public static function publicRoutesCatalog(): array
    {
        return [
            'ibSchedules.types.applicate.classname.index' => [
                'methods' => ['GET', 'HEAD'],
                'controller' => TypeApplicateClassnameController::class,
                'action' => 'index',
                'configTarget' => 'type',
                'configKey' => 'applicateClassname',
                'parametersFileConfig' => null,
                'fieldsGroupConfig' => 'schedules.models.type.fieldsGroupsFiles.applicableModelsElements',
            ],
            'ibSchedules.types.applicate.classname.store' => [
                'methods' => ['POST'],
                'controller' => TypeApplicateClassnameController::class,
                'action' => 'store',
                'configTarget' => 'type',
                'configKey' => 'applicateClassname',
                'parametersFileConfig' => null,
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.types.applicate.index' => [
                'methods' => ['GET', 'HEAD'],
                'controller' => TypeApplicateController::class,
                'action' => 'index',
                'configTarget' => 'type',
                'configKey' => 'applicate',
                'parametersFileConfig' => null,
                'fieldsGroupConfig' => 'schedules.models.type.fieldsGroupsFiles.applicableModels',
            ],
            'ibSchedules.types.typeNotifications.create' => [
                'methods' => ['GET', 'HEAD'],
                'controller' => TypeNotificationCrudController::class,
                'action' => 'createFromType',
                'configTarget' => 'typeNotification',
                'configKey' => 'create',
                'parametersFileConfig' => 'schedules.models.typeNotification.parametersFiles.create',
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.types.typeNotifications.store' => [
                'methods' => ['POST'],
                'controller' => TypeNotificationCrudController::class,
                'action' => 'store',
                'configTarget' => 'typeNotification',
                'configKey' => 'store',
                'parametersFileConfig' => 'schedules.models.typeNotification.parametersFiles.create',
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.types.index' => [
                'methods' => ['GET', 'HEAD'],
                'controller' => TypeCrudController::class,
                'action' => 'index',
                'configTarget' => 'type',
                'configKey' => 'index',
                'parametersFileConfig' => null,
                'fieldsGroupConfig' => 'schedules.models.type.fieldsGroupsFiles.index',
            ],
            'ibSchedules.types.create' => [
                'methods' => ['GET', 'HEAD'],
                'controller' => TypeCrudController::class,
                'action' => 'create',
                'configTarget' => 'type',
                'configKey' => 'create',
                'parametersFileConfig' => 'schedules.models.type.parametersFiles.create',
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.types.store' => [
                'methods' => ['POST'],
                'controller' => TypeCrudController::class,
                'action' => 'store',
                'configTarget' => 'type',
                'configKey' => 'store',
                'parametersFileConfig' => 'schedules.models.type.parametersFiles.create',
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.types.show' => [
                'methods' => ['GET', 'HEAD'],
                'controller' => TypeCrudController::class,
                'action' => 'show',
                'configTarget' => 'type',
                'configKey' => 'show',
                'parametersFileConfig' => 'schedules.models.type.parametersFiles.show',
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.types.edit' => [
                'methods' => ['GET', 'HEAD'],
                'controller' => TypeEditUpdateController::class,
                'action' => 'edit',
                'configTarget' => 'type',
                'configKey' => 'edit',
                'parametersFileConfig' => 'schedules.models.type.parametersFiles.edit',
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.types.update' => [
                'methods' => ['PUT'],
                'controller' => TypeEditUpdateController::class,
                'action' => 'update',
                'configTarget' => 'type',
                'configKey' => 'update',
                'parametersFileConfig' => 'schedules.models.type.parametersFiles.edit',
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.types.destroy' => [
                'methods' => ['DELETE'],
                'controller' => TypeCrudController::class,
                'action' => 'destroy',
                'configTarget' => 'type',
                'configKey' => 'destroy',
                'parametersFileConfig' => null,
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.typeNotifications.index' => [
                'methods' => ['GET', 'HEAD'],
                'controller' => TypeNotificationCrudController::class,
                'action' => 'index',
                'configTarget' => 'typeNotification',
                'configKey' => 'index',
                'parametersFileConfig' => null,
                'fieldsGroupConfig' => 'schedules.models.typeNotification.fieldsGroupsFiles.index',
            ],
            'ibSchedules.typeNotifications.show' => [
                'methods' => ['GET', 'HEAD'],
                'controller' => TypeNotificationCrudController::class,
                'action' => 'show',
                'configTarget' => 'typeNotification',
                'configKey' => 'show',
                'parametersFileConfig' => 'schedules.models.typeNotification.parametersFiles.show',
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.typeNotifications.edit' => [
                'methods' => ['GET', 'HEAD'],
                'controller' => TypeNotificationCrudController::class,
                'action' => 'edit',
                'configTarget' => 'typeNotification',
                'configKey' => 'edit',
                'parametersFileConfig' => 'schedules.models.typeNotification.parametersFiles.edit',
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.typeNotifications.update' => [
                'methods' => ['PUT'],
                'controller' => TypeNotificationCrudController::class,
                'action' => 'update',
                'configTarget' => 'typeNotification',
                'configKey' => 'update',
                'parametersFileConfig' => 'schedules.models.typeNotification.parametersFiles.edit',
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.typeNotifications.destroy' => [
                'methods' => ['DELETE'],
                'controller' => TypeNotificationCrudController::class,
                'action' => 'destroy',
                'configTarget' => 'typeNotification',
                'configKey' => 'destroy',
                'parametersFileConfig' => null,
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.schedules.index' => [
                'methods' => ['GET', 'HEAD'],
                'controller' => ScheduleCrudController::class,
                'action' => 'index',
                'configTarget' => 'schedule',
                'configKey' => 'index',
                'parametersFileConfig' => null,
                'fieldsGroupConfig' => 'schedules.models.schedule.fieldsGroupsFiles.index',
            ],
            'ibSchedules.schedules.manageMany' => [
                'methods' => ['POST'],
                'controller' => ScheduleCrudController::class,
                'action' => 'manageMany',
                'configTarget' => 'schedule',
                'configKey' => 'manageMany',
                'parametersFileConfig' => null,
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.schedules.manage' => [
                'methods' => ['POST'],
                'controller' => ScheduleCrudController::class,
                'action' => 'manage',
                'configTarget' => 'schedule',
                'configKey' => 'manage',
                'parametersFileConfig' => null,
                'fieldsGroupConfig' => null,
            ],
            'ibSchedules.schedules.show' => [
                'methods' => ['GET', 'HEAD'],
                'controller' => ScheduleCrudController::class,
                'action' => 'show',
                'configTarget' => 'schedule',
                'configKey' => 'show',
                'parametersFileConfig' => 'schedules.models.schedule.parametersFiles.show',
                'fieldsGroupConfig' => null,
            ],
        ];
    }

    public static function publicRouteNamesProvider(): array
    {
        $cases = [];

        foreach (array_keys(static::publicRoutesCatalog()) as $name) {
            $cases[$name] = [$name];
        }

        return $cases;
    }

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

    public function testCatalogCoversEveryRegisteredPublicSchedulesRoute(): void
    {
        $registered = collect(Route::getRoutes())
            ->map(fn (RoutingRoute $route): ?string => $route->getName())
            ->filter(fn (?string $name): bool => is_string($name)
                && str_starts_with($name, 'ibSchedules.'))
            ->sort()
            ->values()
            ->all();

        $catalogued = array_keys(static::publicRoutesCatalog());
        sort($catalogued);

        $this->assertSame(
            $registered,
            $catalogued,
            'RouteSmokeTest catalog must list every public ibSchedules.* route exactly once'
        );
    }

    /**
     * @dataProvider publicRouteNamesProvider
     */
    public function testRouteUsesConfiguredHttpMethodControllerActionAndMiddleware(
        string $routeName
    ): void {
        $expectation = static::publicRoutesCatalog()[$routeName];
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "Route [{$routeName}] is not registered");
        $this->assertSame(
            $expectation['methods'],
            $route->methods(),
            "Unexpected HTTP methods for [{$routeName}]"
        );

        $configuredController = Schedules::getController(
            $expectation['configTarget'],
            $expectation['configKey']
        );

        $this->assertSame(
            $expectation['controller'],
            $configuredController
        );
        $this->assertSame(
            $configuredController,
            $route->getControllerClass(),
            "Route [{$routeName}] must use the configured controller override"
        );
        $this->assertSame(
            $expectation['action'],
            $route->getActionMethod()
        );
        $this->assertTrue(
            method_exists($configuredController, $expectation['action']),
            "{$configuredController}::{$expectation['action']} must exist"
        );

        $middleware = $route->gatherMiddleware();

        $this->assertContains('web', $middleware);
        $this->assertContains('auth', $middleware);
        $this->assertContains('schedules.roles', $middleware);
    }

    /**
     * @dataProvider publicRouteNamesProvider
     */
    public function testParametersOrFieldsGroupFileIsPresentWhenConfigured(
        string $routeName
    ): void {
        $expectation = static::publicRoutesCatalog()[$routeName];

        if ($expectation['parametersFileConfig'] !== null) {
            $class = config($expectation['parametersFileConfig']);

            $this->assertNotEmpty(
                $class,
                "Missing parameters file config [{$expectation['parametersFileConfig']}]"
            );
            $this->assertTrue(
                class_exists($class),
                "Parameters file class [{$class}] does not exist"
            );
        }

        if ($expectation['fieldsGroupConfig'] !== null) {
            $class = config($expectation['fieldsGroupConfig']);

            $this->assertNotEmpty(
                $class,
                "Missing fields group config [{$expectation['fieldsGroupConfig']}]"
            );
            $this->assertTrue(
                class_exists($class),
                "Fields group class [{$class}] does not exist"
            );
        }

        if (
            $expectation['parametersFileConfig'] === null
            && $expectation['fieldsGroupConfig'] === null
        ) {
            $this->assertTrue(true);
        }
    }

    public function testGetControllerHonorsRuntimeConfigOverride(): void
    {
        $override = RouteSmokeOverrideTypeIndexController::class;

        config([
            'schedules.models.type.controllers.index' => $override,
        ]);

        $this->assertSame(
            $override,
            Schedules::getController('type', 'index')
        );
    }

    /**
     * @dataProvider publicRouteNamesProvider
     */
    public function testGuestIsDenied(string $routeName): void
    {
        $context = $this->makeRouteContext();
        $call = $this->routeCall($routeName, $context);

        $response = $this->{$call['method']}($call['uri'], $call['payload']);

        $response->assertRedirect(route('login'));
    }

    /**
     * @dataProvider publicRouteNamesProvider
     */
    public function testUserWithoutConfiguredRolesIsDenied(string $routeName): void
    {
        config(['schedules.defaultRoles' => []]);

        $context = $this->makeRouteContext();
        $call = $this->routeCall($routeName, $context);

        $response = $this
            ->actingAs($this->superadmin())
            ->{$call['method']}($call['uri'], $call['payload']);

        $response->assertForbidden();
    }

    /**
     * @dataProvider publicRouteNamesProvider
     */
    public function testHappyPathCompletes(string $routeName): void
    {
        $context = $this->makeRouteContext();
        $call = $this->routeCall($routeName, $context);

        $response = $this
            ->actingAs($this->superadmin())
            ->withoutMiddleware()
            ->withoutExceptionHandling()
            ->{$call['method']}($call['uri'], $call['payload']);

        $this->assertTrue(
            $response->isSuccessful() || $response->isRedirection(),
            "Happy path for [{$routeName}] failed with HTTP {$response->getStatusCode()}"
        );

        if ($routeName === 'ibSchedules.types.store') {
            $this->assertNotNull(
                Type::query()->where('name', 'SmokeCreatedType')->first()
            );
        }

        if ($routeName === 'ibSchedules.types.typeNotifications.store') {
            $this->assertNotNull(
                TypeNotification::query()->where('before', 15)->first()
            );
        }

        if ($routeName === 'ibSchedules.types.destroy') {
            $this->assertSoftDeleted(
                config('schedules.models.type.table'),
                ['id' => $context['destroyableType']->getKey()]
            );
        }

        if ($routeName === 'ibSchedules.typeNotifications.destroy') {
            $this->assertSoftDeleted(
                config('schedules.models.typeNotification.table'),
                ['id' => $context['destroyableTypeNotification']->getKey()]
            );
        }

        if (in_array($routeName, [
            'ibSchedules.schedules.manage',
            'ibSchedules.schedules.manageMany',
        ], true)) {
            $this->assertNotNull($context['schedule']->fresh()->managed_at);
        }
    }

    /**
     * @return array{
     *     type: Type,
     *     typeNotification: TypeNotification,
     *     destroyableType: Type,
     *     destroyableTypeNotification: TypeNotification,
     *     schedule: Schedule,
     *     schedulable: Schedulable
     * }
     */
    protected function makeRouteContext(): array
    {
        $measurementUnit = $this->createMeasurementUnit();
        $type = $this->createType($measurementUnit);
        $typeNotification = $this->createTypeNotification($type, 20);
        $schedulable = $this->createSchedulable(currentValue: 10);
        $applicableSchedulable = Schedulable::query()->create([
            'name' => 'Vehicle for applicate',
            'current_reading' => 40,
        ]);
        $schedule = Schedule::query()->forceCreate([
            'type_id' => $type->getKey(),
            'schedulable_type' => $schedulable->getMorphClass(),
            'schedulable_id' => $schedulable->getKey(),
            'starting_value' => 0,
            'deadline_value' => 100,
        ]);

        $destroyableType = Type::query()->forceCreate([
            'id' => 'smoke-destroy-type',
            'name' => 'Smoke Destroy Type',
            'validity' => 50,
            'measurement_unit_id' => $measurementUnit->getKey(),
            'allow_multiple' => false,
            'roles' => [],
            'models' => [
                [
                    'model' => Schedulable::class,
                    'source' => 'current_reading',
                ],
            ],
        ]);

        $destroyableTypeNotification = TypeNotification::query()->forceCreate([
            'id' => 'smoke-destroy-tn',
            'type_id' => $type->getKey(),
            'before' => 5,
        ]);

        return [
            'type' => $type,
            'typeNotification' => $typeNotification,
            'destroyableType' => $destroyableType,
            'destroyableTypeNotification' => $destroyableTypeNotification,
            'schedule' => $schedule,
            'schedulable' => $schedulable,
            'applicableSchedulable' => $applicableSchedulable,
            'measurementUnit' => $measurementUnit,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{method: string, uri: string, payload: array<string, mixed>}
     */
    protected function routeCall(string $routeName, array $context): array
    {
        return match ($routeName) {
            'ibSchedules.types.applicate.classname.index' => [
                'method' => 'get',
                'uri' => route($routeName, [
                    'type' => $context['type'],
                    'alias' => 'schedulables',
                ]),
                'payload' => [],
            ],
            'ibSchedules.types.applicate.classname.store' => [
                'method' => 'post',
                'uri' => route($routeName, [
                    'type' => $context['type'],
                    'alias' => 'schedulables',
                ]),
                'payload' => [
                    'ids' => [$context['applicableSchedulable']->getKey()],
                ],
            ],
            'ibSchedules.types.applicate.index' => [
                'method' => 'get',
                'uri' => route($routeName, ['type' => $context['type']]),
                'payload' => [],
            ],
            'ibSchedules.types.typeNotifications.create' => [
                'method' => 'get',
                'uri' => route($routeName, ['type' => $context['type']]),
                'payload' => [],
            ],
            'ibSchedules.types.typeNotifications.store' => [
                'method' => 'post',
                'uri' => route($routeName, ['type' => $context['type']]),
                'payload' => [
                    'before' => 15,
                    'urgency' => 1,
                ],
            ],
            'ibSchedules.types.index' => [
                'method' => 'get',
                'uri' => route($routeName),
                'payload' => [],
            ],
            'ibSchedules.types.create' => [
                'method' => 'get',
                'uri' => route($routeName),
                'payload' => [],
            ],
            'ibSchedules.types.store' => [
                'method' => 'post',
                'uri' => route($routeName),
                'payload' => [
                    'name' => 'SmokeCreatedType',
                    'validity' => 100,
                    'percentage_validity' => 99,
                    'allow_multiple' => 0,
                    'measurement_unit_id' => $context['measurementUnit']->getKey(),
                    'models' => [
                        [
                            'model' => Schedulable::class,
                            'source' => 'current_reading',
                            'method' => 'getCurrentReading',
                        ],
                    ],
                    'roles' => [],
                ],
            ],
            'ibSchedules.types.show' => [
                'method' => 'get',
                'uri' => route($routeName, $context['type']),
                'payload' => [],
            ],
            'ibSchedules.types.edit' => [
                'method' => 'get',
                'uri' => route($routeName, $context['type']),
                'payload' => [],
            ],
            'ibSchedules.types.update' => [
                'method' => 'put',
                'uri' => route($routeName, $context['type']),
                'payload' => [
                    'name' => 'Maintenance',
                    'validity' => 100,
                    'percentage_validity' => 99,
                    'allow_multiple' => 0,
                    'measurement_unit_id' => $context['measurementUnit']->getKey(),
                    'models' => [
                        [
                            'model' => Schedulable::class,
                            'source' => 'current_reading',
                            'method' => 'getCurrentReading',
                        ],
                    ],
                    'roles' => [],
                ],
            ],
            'ibSchedules.types.destroy' => [
                'method' => 'delete',
                'uri' => route($routeName, $context['destroyableType']),
                'payload' => [],
            ],
            'ibSchedules.typeNotifications.index' => [
                'method' => 'get',
                'uri' => route($routeName),
                'payload' => [],
            ],
            'ibSchedules.typeNotifications.show' => [
                'method' => 'get',
                'uri' => route($routeName, $context['typeNotification']),
                'payload' => [],
            ],
            'ibSchedules.typeNotifications.edit' => [
                'method' => 'get',
                'uri' => route($routeName, $context['typeNotification']),
                'payload' => [],
            ],
            'ibSchedules.typeNotifications.update' => [
                'method' => 'put',
                'uri' => route($routeName, $context['typeNotification']),
                'payload' => [
                    'before' => 25,
                    'urgency' => 2,
                ],
            ],
            'ibSchedules.typeNotifications.destroy' => [
                'method' => 'delete',
                'uri' => route($routeName, $context['destroyableTypeNotification']),
                'payload' => [],
            ],
            'ibSchedules.schedules.index' => [
                'method' => 'get',
                'uri' => route($routeName),
                'payload' => [],
            ],
            'ibSchedules.schedules.manageMany' => [
                'method' => 'post',
                'uri' => route($routeName),
                'payload' => ['ids' => [$context['schedule']->getKey()]],
            ],
            'ibSchedules.schedules.manage' => [
                'method' => 'post',
                'uri' => route($routeName, $context['schedule']),
                'payload' => [],
            ],
            'ibSchedules.schedules.show' => [
                'method' => 'get',
                'uri' => route($routeName, $context['schedule']),
                'payload' => [],
            ],
            default => throw new \InvalidArgumentException(
                "No HTTP call defined for route [{$routeName}]"
            ),
        };
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

class RouteSmokeOverrideTypeIndexController extends TypeCrudController
{
}

/**
 * Boots with a controller override already in config so route registration
 * binds the overridden class (proves configured override is used at boot).
 */
class RouteSmokeControllerOverrideRegistrationTest extends TestCase
{
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
        $app['config']->set(
            'schedules.models.type.controllers.index',
            RouteSmokeOverrideTypeIndexController::class
        );
    }

    public function testRegisteredRouteUsesControllerConfiguredBeforeBoot(): void
    {
        $route = Route::getRoutes()->getByName('ibSchedules.types.index');

        $this->assertSame(
            RouteSmokeOverrideTypeIndexController::class,
            $route->getControllerClass()
        );
        $this->assertSame(
            RouteSmokeOverrideTypeIndexController::class,
            Schedules::getController('type', 'index')
        );
    }
}
