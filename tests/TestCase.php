<?php

namespace IlBronza\Schedules\Tests;

use IlBronza\MeasurementUnits\MeasurementUnitsServiceProvider;
use IlBronza\Notifications\NotificationsServiceProvider;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Models\TypeNotification;
use IlBronza\Schedules\SchedulesServiceProvider;
use IlBronza\Schedules\Tests\Fixtures\Models\Alpha\CollidingSchedulable as AlphaCollidingSchedulable;
use IlBronza\Schedules\Tests\Fixtures\Models\Beta\CollidingSchedulable as BetaCollidingSchedulable;
use IlBronza\Schedules\Tests\Fixtures\Models\Role;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use RuntimeException;
use Spatie\Activitylog\ActivitylogServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    public static function applicationBasePath(): string
    {
        return __DIR__.'/Fixtures/App';
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActivitylogServiceProvider::class,
            MeasurementUnitsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchedulesServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.locale', 'it');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('activitylog.default_auth_driver', null);
        $app['config']->set('activitylog.enabled', false);
        $app['config']->set('schedules.evaluator.enabled', false);
        $app['config']->set('schedules.notifications.roleModel', Role::class);
        $app['config']->set('schedules.applicableTo', [
            Schedulable::class => 'schedulables',
            AlphaCollidingSchedulable::class => 'alpha-colliding',
            BetaCollidingSchedulable::class => 'beta-colliding',
        ]);
        $app['config']->set('schedules.models.schedule.class', Schedule::class);
        $app['config']->set('schedules.models.type.class', Type::class);
        $app['config']->set(
            'schedules.models.scheduledNotification.class',
            ScheduledNotification::class
        );
        $app['config']->set(
            'schedules.models.scheduledNotificationDispatch.class',
            \IlBronza\Schedules\Models\ScheduledNotificationDispatch::class
        );
        $app['config']->set(
            'schedules.models.typeNotification.class',
            TypeNotification::class
        );
        $app['config']->set(
            'measurementunits.models.measurementUnit.class',
            \IlBronza\Schedules\Tests\Fixtures\Models\MeasurementUnit::class
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->createDatabaseSchema();
    }

    protected function createDatabaseSchema(): void
    {
        $this->createExternalDependencyTables();
        $this->migratePackageSchema();
    }

    /**
     * External host/app tables required by package FKs and fixtures.
     * Package domain tables come only from real migrations.
     */
    protected function createExternalDependencyTables(): void
    {
        $this->createMeasurementUnitsTable();
        $this->createSchedulablesTable();
        $this->createRolesTable();
        $this->createNotificationsTable();
    }

    protected function migratePackageSchema(): void
    {
        $exitCode = Artisan::call('migrate', [
            '--database' => $this->app['config']->get('database.default'),
            '--path' => $this->packageMigrationsPath(),
            '--realpath' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'Schedules package migrations failed with exit code '.$exitCode
                .': '.trim(Artisan::output())
            );
        }
    }

    protected function packageMigrationsPath(): string
    {
        $path = realpath(__DIR__.'/../database/migrations');

        if ($path === false) {
            throw new RuntimeException(
                'Unable to resolve Schedules package migrations path.'
            );
        }

        return $path;
    }

    protected function createMeasurementUnitsTable(): void
    {
        Schema::create(
            config('measurementunits.models.measurementUnit.table'),
            function (Blueprint $table): void {
                $table->string('id', 16)->primary();
                $table->string('name');
                $table->string('description')->nullable();
                $table->string('base_measurement_unit')->nullable();
                $table->decimal('proportion_toward_base_measurement_unit')
                    ->nullable();
                $table->softDeletes();
                $table->timestamps();
            }
        );
    }

    protected function createSchedulablesTable(): void
    {
        Schema::create('schedulables', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->float('current_reading');
            $table->timestamps();
        });
    }

    protected function createRolesTable(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function createNotificationsTable(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->uuid('parent_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('link', 512)->nullable();
            $table->string('link_text', 512)->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });
    }
}
