<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\Schedules\Tests\Fixtures\LegacyV251Schema;
use IlBronza\Schedules\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class UpgradeFromV251Test extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set(
            'database.connections.testing.foreign_key_constraints',
            true
        );
    }

    protected function createDatabaseSchema(): void
    {
        $this->createMeasurementUnitsTable();
        $this->createNotificationsTable();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function testUpgradeFromV251MatchesFreshInstallSchemaAndData(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped(
                'Upgrade coverage runs on the default sqlite testing connection.'
            );
        }

        $tables = $this->packageTables();
        $migrationsPath = $this->packageMigrationsPath();
        $seed = $this->representativeSeedIds();

        $upgradeSchema = $this->buildUpgradeFromV251Snapshot(
            $tables,
            $seed
        );

        $this->resetPackageState($tables, $migrationsPath);

        $freshSchema = $this->buildFreshInstallSnapshot(
            $migrationsPath,
            $tables,
            $seed
        );

        $this->assertSame(
            $freshSchema['schema'],
            $upgradeSchema['schema'],
            'Upgrade from v2.5.1 must produce a schema functionally equivalent to a fresh install.'
        );

        $this->assertSame(
            $freshSchema['data'],
            $upgradeSchema['data'],
            'Upgrade from v2.5.1 must preserve representative data equivalently to a fresh install.'
        );
    }

    protected function buildFreshInstallSnapshot(
        string $migrationsPath,
        array $tables,
        array $seed
    ): array {
        foreach ($tables as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "Fresh install expected [{$table}] absent before migrate."
            );
        }

        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => DB::getDefaultConnection(),
            '--path' => $migrationsPath,
            '--realpath' => true,
        ]));

        $this->insertRepresentativeDataForCurrentSchema($seed);

        return [
            'schema' => $this->schemaSnapshot($tables),
            'data' => $this->dataSnapshot($tables),
        ];
    }

    protected function buildUpgradeFromV251Snapshot(
        array $tables,
        array $seed
    ): array {
        LegacyV251Schema::create();

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Legacy v2.5.1 fixture must create [{$table}]."
            );
        }

        $this->assertTrue(
            Schema::hasColumn($tables['schedules'], 'user_id'),
            'Legacy v2.5.1 schedules table must include user_id.'
        );
        $this->assertFalse(
            Schema::hasColumn($tables['scheduledNotifications'], 'managed_at'),
            'Legacy v2.5.1 scheduled notifications must not include managed_at yet.'
        );

        $this->insertRepresentativeDataForLegacySchema($seed);

        foreach ($this->incrementalMigrationFiles() as $migrationFile) {
            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => DB::getDefaultConnection(),
                '--path' => $migrationFile,
                '--realpath' => true,
            ]));
        }

        $this->assertFalse(
            Schema::hasColumn($tables['schedules'], 'user_id'),
            'Upgrade must drop schedules.user_id.'
        );
        $this->assertTrue(
            Schema::hasColumn($tables['scheduledNotifications'], 'managed_at'),
            'Upgrade must add scheduled_notifications.managed_at.'
        );

        return [
            'schema' => $this->schemaSnapshot($tables),
            'data' => $this->dataSnapshot($tables),
        ];
    }

    protected function insertRepresentativeDataForLegacySchema(array $seed): void
    {
        $now = $seed['now'];
        $measurementUnits = config(
            'measurementunits.models.measurementUnit.table'
        );
        $types = config('schedules.models.type.table');
        $schedules = config('schedules.models.schedule.table');
        $typeNotifications = config('schedules.models.typeNotification.table');
        $scheduledNotifications = config(
            'schedules.models.scheduledNotification.table'
        );

        DB::table('users')->insert([
            'id' => $seed['user_id'],
            'name' => 'Legacy Operator',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($measurementUnits)->insert([
            'id' => $seed['measurement_unit_id'],
            'name' => 'Reading',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($types)->insert([
            'id' => $seed['type_id'],
            'name' => 'Maintenance',
            'validity' => '100',
            'measurement_unit_id' => $seed['measurement_unit_id'],
            'allow_multiple' => false,
            'percentage_validity' => 80.5,
            'roles' => json_encode(['schedules']),
            'models' => json_encode(['vehicle']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($schedules)->insert([
            'id' => $seed['schedule_id'],
            'user_id' => $seed['user_id'],
            'type_id' => $seed['type_id'],
            'schedulable_type' => $seed['schedulable_type'],
            'schedulable_id' => $seed['schedulable_id'],
            'starting_value' => '10',
            'deadline_value' => '100',
            'field' => 'current_reading',
            'expired_at' => null,
            'managed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($typeNotifications)->insert([
            'id' => $seed['type_notification_id'],
            'type_id' => $seed['type_id'],
            'before' => '20',
            'urgency' => 1,
            'repeat_every' => null,
            'repeat_every_measurement_unit_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('notifications')->insert([
            'id' => $seed['notification_id'],
            'type' => 'IlBronza\\Schedules\\Notifications\\ScheduleThresholdReachedNotification',
            'notifiable_type' => 'user',
            'notifiable_id' => $seed['user_id'],
            'data' => json_encode(['schedule_id' => $seed['schedule_id']]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($scheduledNotifications)->insert([
            'id' => $seed['scheduled_notification_id'],
            'schedule_id' => $seed['schedule_id'],
            'notification_id' => $seed['notification_id'],
            'type_notification_id' => $seed['type_notification_id'],
            'deadline_value' => '80',
            'expired_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function insertRepresentativeDataForCurrentSchema(array $seed): void
    {
        $now = $seed['now'];
        $measurementUnits = config(
            'measurementunits.models.measurementUnit.table'
        );
        $types = config('schedules.models.type.table');
        $schedules = config('schedules.models.schedule.table');
        $typeNotifications = config('schedules.models.typeNotification.table');
        $scheduledNotifications = config(
            'schedules.models.scheduledNotification.table'
        );

        DB::table('users')->insert([
            'id' => $seed['user_id'],
            'name' => 'Legacy Operator',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($measurementUnits)->insert([
            'id' => $seed['measurement_unit_id'],
            'name' => 'Reading',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($types)->insert([
            'id' => $seed['type_id'],
            'name' => 'Maintenance',
            'validity' => '100',
            'measurement_unit_id' => $seed['measurement_unit_id'],
            'allow_multiple' => false,
            'percentage_validity' => 80.5,
            'roles' => json_encode(['schedules']),
            'models' => json_encode(['vehicle']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($schedules)->insert([
            'id' => $seed['schedule_id'],
            'type_id' => $seed['type_id'],
            'schedulable_type' => $seed['schedulable_type'],
            'schedulable_id' => $seed['schedulable_id'],
            'starting_value' => '10',
            'deadline_value' => '100',
            'field' => 'current_reading',
            'expired_at' => null,
            'managed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($typeNotifications)->insert([
            'id' => $seed['type_notification_id'],
            'type_id' => $seed['type_id'],
            'before' => '20',
            'urgency' => 1,
            'repeat_every' => null,
            'repeat_every_measurement_unit_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('notifications')->insert([
            'id' => $seed['notification_id'],
            'type' => 'IlBronza\\Schedules\\Notifications\\ScheduleThresholdReachedNotification',
            'notifiable_type' => 'user',
            'notifiable_id' => $seed['user_id'],
            'data' => json_encode(['schedule_id' => $seed['schedule_id']]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($scheduledNotifications)->insert([
            'id' => $seed['scheduled_notification_id'],
            'schedule_id' => $seed['schedule_id'],
            'notification_id' => $seed['notification_id'],
            'type_notification_id' => $seed['type_notification_id'],
            'deadline_value' => '80',
            'expired_at' => null,
            'managed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function representativeSeedIds(): array
    {
        return [
            'now' => '2026-08-15 10:00:00',
            'user_id' => 7,
            'measurement_unit_id' => 'reading',
            'type_id' => 'maintenance',
            'schedule_id' => '11111111-1111-1111-1111-111111111111',
            'schedulable_type' => 'IlBronza\\Schedules\\Tests\\Fixtures\\Models\\Schedulable',
            'schedulable_id' => '22222222-2222-2222-2222-222222222222',
            'type_notification_id' => 'threshold-20',
            'notification_id' => '33333333-3333-3333-3333-333333333333',
            'scheduled_notification_id' => '44444444-4444-4444-4444-444444444444',
        ];
    }

    protected function schemaSnapshot(array $tables): array
    {
        $snapshot = [];

        foreach ($tables as $key => $table) {
            $columns = [];

            foreach (Schema::getColumns($table) as $column) {
                $columns[$column['name']] = [
                    'type_name' => $this->normalizeTypeName(
                        (string) $column['type_name']
                    ),
                    'nullable' => (bool) $column['nullable'],
                ];
            }

            ksort($columns);

            $indexes = [];

            foreach (Schema::getIndexes($table) as $index) {
                $indexes[] = [
                    'columns' => $index['columns'],
                    'unique' => (bool) ($index['unique'] ?? false),
                    'primary' => (bool) ($index['primary'] ?? false),
                ];
            }

            usort(
                $indexes,
                static function (array $left, array $right): int {
                    return [
                        $left['columns'],
                        $left['unique'],
                        $left['primary'],
                    ] <=> [
                        $right['columns'],
                        $right['unique'],
                        $right['primary'],
                    ];
                }
            );

            $foreignKeys = [];

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $foreignKeys[] = [
                    'columns' => $foreignKey['columns'],
                    'foreign_table' => $foreignKey['foreign_table'],
                    'foreign_columns' => $foreignKey['foreign_columns'],
                ];
            }

            usort(
                $foreignKeys,
                static function (array $left, array $right): int {
                    return [
                        $left['columns'],
                        $left['foreign_table'],
                        $left['foreign_columns'],
                    ] <=> [
                        $right['columns'],
                        $right['foreign_table'],
                        $right['foreign_columns'],
                    ];
                }
            );

            $snapshot[$key] = [
                'columns' => $columns,
                'indexes' => $indexes,
                'foreign_keys' => $foreignKeys,
            ];
        }

        return $snapshot;
    }

    protected function normalizeTypeName(string $typeName): string
    {
        $normalized = strtolower($typeName);

        return match ($normalized) {
            'varchar', 'character varying', 'nvarchar' => 'string',
            'char', 'nchar', 'character' => 'string',
            'tinyint', 'boolean', 'bool' => 'boolean',
            'float', 'real', 'double', 'double precision' => 'float',
            'datetime', 'timestamp' => 'datetime',
            default => $normalized,
        };
    }

    protected function dataSnapshot(array $tables): array
    {
        $snapshot = [];

        foreach ($tables as $key => $table) {
            $rows = DB::table($table)->orderBy('id')->get()->map(
                function ($row): array {
                    $data = (array) $row;

                    foreach ($data as $column => $value) {
                        $data[$column] = $this->normalizeDataValue($value);
                    }

                    ksort($data);

                    return $data;
                }
            )->all();

            $snapshot[$key] = $rows;
        }

        return $snapshot;
    }

    protected function normalizeDataValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_string($value) && is_numeric($value)) {
            if (str_contains($value, '.')) {
                return (float) $value;
            }
        }

        if (is_float($value)) {
            return round($value, 4);
        }

        return $value;
    }

    protected function resetPackageState(
        array $tables,
        string $migrationsPath
    ): void {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists(
            config('schedules.models.scheduledNotificationDispatch.table')
        );

        foreach (array_reverse($tables) as $table) {
            Schema::dropIfExists($table);
        }

        DB::table('users')->delete();
        DB::table(
            config('measurementunits.models.measurementUnit.table')
        )->delete();
        DB::table('notifications')->delete();

        Schema::enableForeignKeyConstraints();

        $migrationNames = [];

        foreach (glob($migrationsPath.'/*.php') ?: [] as $file) {
            $migrationNames[] = basename($file, '.php');
        }

        if ($migrationNames === []) {
            throw new RuntimeException(
                'No package migration files found while resetting upgrade test state.'
            );
        }

        DB::table('migrations')->whereIn('migration', $migrationNames)->delete();
    }

    protected function packageTables(): array
    {
        return [
            'types' => config('schedules.models.type.table'),
            'schedules' => config('schedules.models.schedule.table'),
            'typeNotifications' => config(
                'schedules.models.typeNotification.table'
            ),
            'scheduledNotifications' => config(
                'schedules.models.scheduledNotification.table'
            ),
        ];
    }

    /**
     * @return list<string>
     */
    protected function incrementalMigrationFiles(): array
    {
        $path = $this->packageMigrationsPath();
        $files = glob($path.'/2026_*.php') ?: [];
        sort($files);

        if ($files === []) {
            throw new RuntimeException(
                'Expected incremental 2026_* migrations for upgrade from v2.5.1.'
            );
        }

        return $files;
    }
}
