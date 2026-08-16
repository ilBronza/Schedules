<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\Schedules\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;

class MigrationsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        if (getenv('SCHEDULES_TEST_MYSQL') !== '1') {
            return;
        }

        $password = getenv('DB_PASSWORD');

        $app['config']->set('database.default', 'mysql_testing');
        $app['config']->set('database.connections.mysql_testing', [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => getenv('DB_PORT') ?: '3306',
            'database' => getenv('DB_DATABASE') ?: 'schedules_testing',
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => $password === false ? '' : $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'foreign_key_constraints' => true,
        ]);
    }

    protected function createDatabaseSchema(): void
    {
        $this->createMeasurementUnitsTable();
        $this->createNotificationsTable();
    }

    public function testFreshInstallUpDownAndForeignKeysOnSqlite(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite migration coverage requires the default sqlite testing connection.');
        }

        $this->assertPackageMigrationsUpDownAndForeignKeys();
    }

    #[Group('mysql')]
    public function testFreshInstallUpDownAndForeignKeysOnMysql(): void
    {
        if (getenv('SCHEDULES_TEST_MYSQL') !== '1') {
            $this->markTestSkipped(
                'MySQL migration coverage runs in CI when SCHEDULES_TEST_MYSQL=1.'
            );
        }

        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException(
                'SCHEDULES_TEST_MYSQL=1 requires database.default to use a MySQL connection.'
            );
        }

        $this->assertPackageMigrationsUpDownAndForeignKeys();
    }

    public function testUniqueMigrationSucceedsOnCleanUpgrade(): void
    {
        $this->migratePackageExceptUnique();

        $fixture = $this->seedScheduledNotificationUpgradeFixture();
        $scheduledNotifications = config(
            'schedules.models.scheduledNotification.table'
        );
        $now = now();

        DB::table($scheduledNotifications)->insert([
            [
                'id' => (string) Str::uuid(),
                'schedule_id' => $fixture['schedule_id'],
                'notification_id' => null,
                'type_notification_id' => $fixture['type_notification_id'],
                'deadline_value' => '90',
                'expired_at' => null,
                'managed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->assertSame(0, $this->runUniqueMigration());

        $this->assertTrue(
            $this->indexExists(
                $scheduledNotifications,
                'scheduled_notifications_schedule_type_unique'
            )
        );
    }

    public function testUniqueMigrationFailsOnDuplicatePairsWithDiagnostics(): void
    {
        $this->migratePackageExceptUnique();

        $fixture = $this->seedScheduledNotificationUpgradeFixture();
        $scheduledNotifications = config(
            'schedules.models.scheduledNotification.table'
        );
        $now = now();
        $scheduleId = $fixture['schedule_id'];
        $typeNotificationId = $fixture['type_notification_id'];

        DB::table($scheduledNotifications)->insert([
            [
                'id' => (string) Str::uuid(),
                'schedule_id' => $scheduleId,
                'notification_id' => null,
                'type_notification_id' => $typeNotificationId,
                'deadline_value' => '90',
                'expired_at' => null,
                'managed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'schedule_id' => $scheduleId,
                'notification_id' => null,
                'type_notification_id' => $typeNotificationId,
                'deadline_value' => '85',
                'expired_at' => null,
                'managed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        try {
            $this->runUniqueMigration();
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'duplicate pairs found',
                $exception->getMessage()
            );
            $this->assertStringContainsString(
                "schedule_id={$scheduleId},type_notification_id={$typeNotificationId}",
                $exception->getMessage()
            );
            $this->assertFalse(
                $this->indexExists(
                    $scheduledNotifications,
                    'scheduled_notifications_schedule_type_unique'
                )
            );

            return;
        }

        $this->fail(
            'Expected RuntimeException with duplicate pair diagnostics before adding the unique index.'
        );
    }

    public function testUniqueMigrationFailsOnDuplicateNullTypeNotificationIdsWithDiagnostics(): void
    {
        $this->migratePackageExceptUnique();

        $fixture = $this->seedScheduledNotificationUpgradeFixture();
        $scheduledNotifications = config(
            'schedules.models.scheduledNotification.table'
        );
        $now = now();
        $scheduleId = $fixture['schedule_id'];

        Schema::table($scheduledNotifications, function (Blueprint $table): void {
            $table->string('type_notification_id')->nullable()->change();
        });

        DB::table($scheduledNotifications)->insert([
            [
                'id' => (string) Str::uuid(),
                'schedule_id' => $scheduleId,
                'notification_id' => null,
                'type_notification_id' => null,
                'deadline_value' => '90',
                'expired_at' => null,
                'managed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'schedule_id' => $scheduleId,
                'notification_id' => null,
                'type_notification_id' => null,
                'deadline_value' => '85',
                'expired_at' => null,
                'managed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        try {
            $this->runUniqueMigration();
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'duplicate pairs found',
                $exception->getMessage()
            );
            $this->assertStringContainsString(
                "schedule_id={$scheduleId},type_notification_id=null",
                $exception->getMessage()
            );
            $this->assertFalse(
                $this->indexExists(
                    $scheduledNotifications,
                    'scheduled_notifications_schedule_type_unique'
                )
            );

            return;
        }

        $this->fail(
            'Expected RuntimeException with null type_notification_id duplicate diagnostics before adding the unique index.'
        );
    }

    protected function assertPackageMigrationsUpDownAndForeignKeys(): void
    {
        $types = config('schedules.models.type.table');
        $schedules = config('schedules.models.schedule.table');
        $typeNotifications = config('schedules.models.typeNotification.table');
        $scheduledNotifications = config(
            'schedules.models.scheduledNotification.table'
        );
        $measurementUnits = config(
            'measurementunits.models.measurementUnit.table'
        );
        $migrationsPath = $this->packageMigrationsPath();

        foreach ([
            $types,
            $schedules,
            $typeNotifications,
            $scheduledNotifications,
        ] as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "Package table [{$table}] must not be created manually before migrate."
            );
        }

        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => DB::getDefaultConnection(),
            '--path' => $migrationsPath,
            '--realpath' => true,
        ]));

        $this->assertTrue(Schema::hasTable($types));
        $this->assertTrue(Schema::hasTable($schedules));
        $this->assertTrue(Schema::hasTable($typeNotifications));
        $this->assertTrue(Schema::hasTable($scheduledNotifications));
        $this->assertTrue(Schema::hasTable(
            config('schedules.models.scheduledNotificationDispatch.table')
        ));

        $this->assertTrue(Schema::hasColumn($types, 'percentage_validity'));
        $this->assertTrue(Schema::hasColumn($schedules, 'field'));
        $this->assertTrue(Schema::hasColumn($schedules, 'managed_at'));
        $this->assertTrue(Schema::hasColumn($scheduledNotifications, 'managed_at'));
        $this->assertTrue(Schema::hasColumn($typeNotifications, 'last_repetition'));
        $this->assertTrue(Schema::hasColumn(
            $scheduledNotifications,
            'last_dispatched_value'
        ));
        $this->assertTrue(
            $this->indexExists(
                $scheduledNotifications,
                'scheduled_notifications_schedule_type_unique'
            )
        );

        $this->assertForeignKeyExists($types, 'measurement_unit_id', $measurementUnits);
        $this->assertForeignKeyExists($schedules, 'type_id', $types);
        $this->assertForeignKeyExists($typeNotifications, 'type_id', $types);
        $this->assertForeignKeyExists(
            $typeNotifications,
            'repeat_every_measurement_unit_id',
            $measurementUnits
        );
        $this->assertForeignKeyExists($scheduledNotifications, 'schedule_id', $schedules);
        $this->assertForeignKeyExists(
            $scheduledNotifications,
            'notification_id',
            'notifications'
        );
        $this->assertForeignKeyExists(
            $scheduledNotifications,
            'type_notification_id',
            $typeNotifications
        );

        foreach (['type_id', 'schedulable_type', 'schedulable_id'] as $column) {
            $this->assertColumnNotNullable($schedules, $column);
        }

        foreach (['type_id', 'before'] as $column) {
            $this->assertColumnNotNullable($typeNotifications, $column);
        }

        $this->assertColumnNotNullable(
            $scheduledNotifications,
            'type_notification_id'
        );

        $this->assertForeignKeysEnforceReferentialIntegrity(
            $measurementUnits,
            $types,
            $schedules
        );

        $this->assertSame(0, Artisan::call('migrate:rollback', [
            '--database' => DB::getDefaultConnection(),
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--step' => 1,
        ]));

        $this->assertFalse(Schema::hasTable(
            config('schedules.models.scheduledNotificationDispatch.table')
        ));
        $this->assertFalse(Schema::hasColumn($typeNotifications, 'last_repetition'));
        $this->assertFalse(Schema::hasColumn(
            $scheduledNotifications,
            'last_dispatched_value'
        ));
        $this->assertTrue(
            $this->indexExists(
                $scheduledNotifications,
                'scheduled_notifications_schedule_type_unique'
            )
        );
        $this->assertColumnNotNullable($schedules, 'schedulable_type');

        $this->assertSame(0, Artisan::call('migrate:rollback', [
            '--database' => DB::getDefaultConnection(),
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--step' => 1,
        ]));

        $this->assertTrue(
            $this->indexExists(
                $scheduledNotifications,
                'scheduled_notifications_schedule_type_unique'
            )
        );
        $this->assertTrue($this->columnIsNullable($schedules, 'schedulable_type'));

        $this->assertSame(0, Artisan::call('migrate:rollback', [
            '--database' => DB::getDefaultConnection(),
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--step' => 1,
        ]));

        $this->assertFalse(
            $this->indexExists(
                $scheduledNotifications,
                'scheduled_notifications_schedule_type_unique'
            )
        );
        $this->assertTrue(Schema::hasColumn($scheduledNotifications, 'managed_at'));

        $this->assertSame(0, Artisan::call('migrate:rollback', [
            '--database' => DB::getDefaultConnection(),
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--step' => 1,
        ]));

        $this->assertFalse(Schema::hasColumn($scheduledNotifications, 'managed_at'));

        $this->assertSame(0, Artisan::call('migrate:rollback', [
            '--database' => DB::getDefaultConnection(),
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--step' => 6,
        ]));

        $this->assertFalse(Schema::hasTable($types));
        $this->assertFalse(Schema::hasTable($schedules));
        $this->assertFalse(Schema::hasTable($typeNotifications));
        $this->assertFalse(Schema::hasTable($scheduledNotifications));
    }

    protected function migratePackageExceptUnique(): void
    {
        $this->migratePackageBefore(
            '2026_08_14_134900_add_unique_type_to_scheduled_notifications_table.php'
        );
    }

    protected function migratePackageBefore(string $excludedBasename): void
    {
        $path = $this->packageMigrationsPath();
        $files = glob($path.'/*.php');

        if ($files === false) {
            throw new RuntimeException(
                'Unable to list Schedules package migration files.'
            );
        }

        sort($files);

        foreach ($files as $file) {
            if (basename($file) >= $excludedBasename) {
                continue;
            }

            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => DB::getDefaultConnection(),
                '--path' => $file,
                '--realpath' => true,
            ]));
        }
    }

    protected function runUniqueMigration(): int
    {
        return Artisan::call('migrate', [
            '--database' => DB::getDefaultConnection(),
            '--path' => $this->packageMigrationsPath()
                .'/2026_08_14_134900_add_unique_type_to_scheduled_notifications_table.php',
            '--realpath' => true,
        ]);
    }

    protected function seedScheduledNotificationUpgradeFixture(): array
    {
        $measurementUnits = config(
            'measurementunits.models.measurementUnit.table'
        );
        $types = config('schedules.models.type.table');
        $schedules = config('schedules.models.schedule.table');
        $typeNotifications = config('schedules.models.typeNotification.table');
        $now = now();
        $scheduleId = (string) Str::uuid();
        $typeNotificationId = 'threshold-90';

        DB::table($measurementUnits)->insert([
            'id' => 'reading',
            'name' => 'Reading',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($types)->insert([
            'id' => 'maintenance',
            'name' => 'Maintenance',
            'validity' => '100',
            'measurement_unit_id' => 'reading',
            'allow_multiple' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($schedules)->insert([
            'id' => $scheduleId,
            'type_id' => 'maintenance',
            'schedulable_type' => 'App\\Models\\Vehicle',
            'schedulable_id' => '1',
            'deadline_value' => '100',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($typeNotifications)->insert([
            'id' => $typeNotificationId,
            'type_id' => 'maintenance',
            'before' => '10',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'schedule_id' => $scheduleId,
            'type_notification_id' => $typeNotificationId,
        ];
    }

    protected function assertForeignKeysEnforceReferentialIntegrity(
        string $measurementUnits,
        string $types,
        string $schedules
    ): void {
        if (DB::getDriverName() === 'sqlite') {
            $enabled = (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys;

            if ($enabled !== 1) {
                throw new RuntimeException(
                    'SQLite foreign_keys pragma must be ON to verify package foreign keys.'
                );
            }
        }

        $now = now();

        DB::table($measurementUnits)->insert([
            'id' => 'reading',
            'name' => 'Reading',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($types)->insert([
            'id' => 'maintenance',
            'name' => 'Maintenance',
            'validity' => '100',
            'measurement_unit_id' => 'reading',
            'allow_multiple' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($schedules)->insert([
            'id' => (string) Str::uuid(),
            'type_id' => 'maintenance',
            'schedulable_type' => 'App\\Models\\Vehicle',
            'schedulable_id' => '1',
            'deadline_value' => '100',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            DB::table($schedules)->insert([
                'id' => (string) Str::uuid(),
                'type_id' => 'missing-type',
                'schedulable_type' => 'App\\Models\\Vehicle',
                'schedulable_id' => '1',
                'deadline_value' => '100',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (QueryException $exception) {
            $this->assertNotSame('', $exception->getMessage());

            return;
        }

        $this->fail('Expected QueryException when inserting a schedule with an invalid type_id foreign key.');
    }

    protected function assertColumnNotNullable(string $table, string $column): void
    {
        $this->assertFalse(
            $this->columnIsNullable($table, $column),
            "Expected [{$table}.{$column}] to be NOT NULL."
        );
    }

    protected function columnIsNullable(string $table, string $column): bool
    {
        foreach (Schema::getColumns($table) as $schemaColumn) {
            if (($schemaColumn['name'] ?? null) === $column) {
                return (bool) ($schemaColumn['nullable'] ?? false);
            }
        }

        throw new RuntimeException(
            "Unable to resolve nullability for [{$table}.{$column}]."
        );
    }

    protected function assertForeignKeyExists(
        string $table,
        string $column,
        string $foreignTable
    ): void {
        $foreignKeys = Schema::getForeignKeys($table);

        foreach ($foreignKeys as $foreignKey) {
            $columns = $foreignKey['columns'] ?? [];
            $references = $foreignKey['foreign_table'] ?? null;

            if (in_array($column, $columns, true) && $references === $foreignTable) {
                $this->assertTrue(true);

                return;
            }
        }

        $this->fail(
            "Expected foreign key on [{$table}.{$column}] referencing [{$foreignTable}]."
        );
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
}
