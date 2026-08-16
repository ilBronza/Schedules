<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\TypeNotification;
use IlBronza\Schedules\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class ScheduleDomainNullabilityTest extends TestCase
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
    }

    public function testFreshInstallRequiredDomainColumnsAreNotNullable(): void
    {
        $this->migratePackage();

        $this->assertColumnIsNotNullable(
            config('schedules.models.schedule.table'),
            'type_id'
        );
        $this->assertColumnIsNotNullable(
            config('schedules.models.schedule.table'),
            'schedulable_type'
        );
        $this->assertColumnIsNotNullable(
            config('schedules.models.schedule.table'),
            'schedulable_id'
        );
        $this->assertColumnIsNotNullable(
            config('schedules.models.typeNotification.table'),
            'type_id'
        );
        $this->assertColumnIsNotNullable(
            config('schedules.models.typeNotification.table'),
            'before'
        );
        $this->assertColumnIsNotNullable(
            config('schedules.models.scheduledNotification.table'),
            'type_notification_id'
        );
    }

    public function testSqlCannotInsertScheduleMissingRequiredDomainFields(): void
    {
        $this->migratePackage();
        $fixture = $this->seedValidType();
        $schedules = config('schedules.models.schedule.table');
        $now = now();

        try {
            DB::table($schedules)->insert([
                'id' => (string) Str::uuid(),
                'type_id' => $fixture['type_id'],
                'deadline_value' => '100',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (QueryException $exception) {
            $this->assertNotSame('', $exception->getMessage());

            return;
        }

        $this->fail(
            'Expected QueryException when inserting a schedule without schedulable morph columns.'
        );
    }

    public function testNullabilityMigrationFailsListingLegacyNullIds(): void
    {
        $this->migratePackageExceptNullability();

        $schedules = config('schedules.models.schedule.table');
        $typeNotifications = config('schedules.models.typeNotification.table');
        $scheduledNotifications = config(
            'schedules.models.scheduledNotification.table'
        );

        $this->makeColumnsNullableForLegacyFixture([
            $schedules => ['type_id', 'schedulable_type', 'schedulable_id'],
            $typeNotifications => ['type_id', 'before'],
            $scheduledNotifications => ['type_notification_id'],
        ]);

        $fixture = $this->seedValidType();
        $now = now();
        $scheduleId = (string) Str::uuid();
        $typeNotificationId = 'threshold-null';
        $scheduledNotificationId = (string) Str::uuid();

        DB::table($schedules)->insert([
            'id' => $scheduleId,
            'type_id' => null,
            'schedulable_type' => null,
            'schedulable_id' => null,
            'deadline_value' => '100',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($typeNotifications)->insert([
            'id' => $typeNotificationId,
            'type_id' => null,
            'before' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($scheduledNotifications)->insert([
            'id' => $scheduledNotificationId,
            'schedule_id' => $scheduleId,
            'notification_id' => null,
            'type_notification_id' => null,
            'deadline_value' => '80',
            'expired_at' => null,
            'managed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            $this->runNullabilityMigration();
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();

            $this->assertStringContainsString($scheduleId, $message);
            $this->assertStringContainsString($typeNotificationId, $message);
            $this->assertStringContainsString($scheduledNotificationId, $message);
            $this->assertColumnIsNullable($schedules, 'type_id');
            $this->assertColumnIsNullable($typeNotifications, 'before');
            $this->assertColumnIsNullable(
                $scheduledNotifications,
                'type_notification_id'
            );

            return;
        }

        $this->fail(
            'Expected RuntimeException listing legacy null IDs before adding NOT NULL constraints.'
        );
    }

    public function testNullabilityMigrationSucceedsOnCleanLegacyData(): void
    {
        $this->migratePackageExceptNullability();

        $schedules = config('schedules.models.schedule.table');
        $typeNotifications = config('schedules.models.typeNotification.table');
        $scheduledNotifications = config(
            'schedules.models.scheduledNotification.table'
        );

        $this->makeColumnsNullableForLegacyFixture([
            $schedules => ['type_id', 'schedulable_type', 'schedulable_id'],
            $typeNotifications => ['type_id', 'before'],
            $scheduledNotifications => ['type_notification_id'],
        ]);

        $fixture = $this->seedValidType();
        $now = now();
        $scheduleId = (string) Str::uuid();
        $typeNotificationId = 'threshold-20';
        $scheduledNotificationId = (string) Str::uuid();

        DB::table($schedules)->insert([
            'id' => $scheduleId,
            'type_id' => $fixture['type_id'],
            'schedulable_type' => 'IlBronza\\Schedules\\Tests\\Fixtures\\Models\\Schedulable',
            'schedulable_id' => (string) Str::uuid(),
            'deadline_value' => '100',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($typeNotifications)->insert([
            'id' => $typeNotificationId,
            'type_id' => $fixture['type_id'],
            'before' => '20',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($scheduledNotifications)->insert([
            'id' => $scheduledNotificationId,
            'schedule_id' => $scheduleId,
            'notification_id' => null,
            'type_notification_id' => $typeNotificationId,
            'deadline_value' => '80',
            'expired_at' => null,
            'managed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->assertSame(0, $this->runNullabilityMigration());

        $this->assertColumnIsNotNullable($schedules, 'type_id');
        $this->assertColumnIsNotNullable($schedules, 'schedulable_type');
        $this->assertColumnIsNotNullable($schedules, 'schedulable_id');
        $this->assertColumnIsNotNullable($typeNotifications, 'type_id');
        $this->assertColumnIsNotNullable($typeNotifications, 'before');
        $this->assertColumnIsNotNullable(
            $scheduledNotifications,
            'type_notification_id'
        );
    }

    public function testScheduleGettersRejectNullDomain(): void
    {
        $schedule = new Schedule();
        $schedule->id = (string) Str::uuid();
        $schedule->type_id = null;
        $schedule->setRelation('schedulable', null);

        try {
            $schedule->getSchedulable();
            $this->fail('Expected RuntimeException for null schedulable.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($schedule->id, $exception->getMessage());
        }

        try {
            $schedule->getType();
            $this->fail('Expected RuntimeException for null type_id.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($schedule->id, $exception->getMessage());
        }
    }

    public function testTypeNotificationGetterRejectsNullBefore(): void
    {
        $typeNotification = new TypeNotification();
        $typeNotification->id = 'threshold-null';
        $typeNotification->before = null;

        try {
            $typeNotification->getBefore();
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                $typeNotification->id,
                $exception->getMessage()
            );

            return;
        }

        $this->fail('Expected RuntimeException for null before.');
    }

    public function testScheduledNotificationGetterRejectsNullTypeNotification(): void
    {
        $scheduledNotification = new ScheduledNotification();
        $scheduledNotification->id = (string) Str::uuid();
        $scheduledNotification->setRelation('typeNotification', null);

        try {
            $scheduledNotification->getTypeNotification();
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                $scheduledNotification->id,
                $exception->getMessage()
            );

            return;
        }

        $this->fail('Expected RuntimeException for null type notification.');
    }

    /**
     * @return array{type_id: string}
     */
    protected function seedValidType(): array
    {
        $measurementUnits = config(
            'measurementunits.models.measurementUnit.table'
        );
        $types = config('schedules.models.type.table');
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

        return ['type_id' => 'maintenance'];
    }

    /**
     * @param  array<string, list<string>>  $columnsByTable
     */
    protected function makeColumnsNullableForLegacyFixture(array $columnsByTable): void
    {
        foreach ($columnsByTable as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->string($column)->nullable()->change();
                }
            });
        }
    }

    protected function assertColumnIsNotNullable(string $table, string $column): void
    {
        foreach (Schema::getColumns($table) as $definition) {
            if (($definition['name'] ?? null) !== $column) {
                continue;
            }

            $this->assertFalse(
                (bool) ($definition['nullable'] ?? true),
                "Expected [{$table}.{$column}] to be NOT NULL."
            );

            return;
        }

        $this->fail("Column [{$table}.{$column}] was not found.");
    }

    protected function assertColumnIsNullable(string $table, string $column): void
    {
        foreach (Schema::getColumns($table) as $definition) {
            if (($definition['name'] ?? null) !== $column) {
                continue;
            }

            $this->assertTrue(
                (bool) ($definition['nullable'] ?? false),
                "Expected [{$table}.{$column}] to remain nullable after failed migration."
            );

            return;
        }

        $this->fail("Column [{$table}.{$column}] was not found.");
    }

    protected function migratePackage(): void
    {
        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => DB::getDefaultConnection(),
            '--path' => $this->packageMigrationsPath(),
            '--realpath' => true,
        ]));
    }

    protected function migratePackageExceptNullability(): void
    {
        $path = $this->packageMigrationsPath();
        $files = glob($path.'/*.php');

        if ($files === false) {
            throw new RuntimeException(
                'Unable to list Schedules package migration files.'
            );
        }

        sort($files);

        $excluded = $this->nullabilityMigrationBasename();

        foreach ($files as $file) {
            if (basename($file) >= $excluded) {
                continue;
            }

            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => DB::getDefaultConnection(),
                '--path' => $file,
                '--realpath' => true,
            ]));
        }
    }

    protected function runNullabilityMigration(): int
    {
        return Artisan::call('migrate', [
            '--database' => DB::getDefaultConnection(),
            '--path' => $this->packageMigrationsPath()
                .'/'.$this->nullabilityMigrationBasename(),
            '--realpath' => true,
        ]);
    }

    protected function nullabilityMigrationBasename(): string
    {
        return '2026_08_15_120000_enforce_required_schedule_domain_columns.php';
    }
}
