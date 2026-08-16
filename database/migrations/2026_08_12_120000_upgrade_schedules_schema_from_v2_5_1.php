<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schedules = config('schedules.models.schedule.table');
        $types = config('schedules.models.type.table');
        $typeNotifications = config('schedules.models.typeNotification.table');
        $scheduledNotifications = config(
            'schedules.models.scheduledNotification.table'
        );

        $this->assertPackageTablesExist([
            $schedules,
            $types,
            $typeNotifications,
            $scheduledNotifications,
        ]);

        if (Schema::hasColumn($schedules, 'user_id')) {
            Schema::table($schedules, function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }

        $this->widenNullableForeignString(
            $schedules,
            'type_id',
            $types
        );
        $this->widenNullableString($schedules, 'schedulable_id');

        $this->widenNullableForeignString(
            $typeNotifications,
            'type_id',
            $types
        );

        $this->widenNullableForeignString(
            $scheduledNotifications,
            'type_notification_id',
            $typeNotifications
        );
    }

    public function down(): void
    {
        $schedules = config('schedules.models.schedule.table');
        $types = config('schedules.models.type.table');
        $typeNotifications = config('schedules.models.typeNotification.table');
        $scheduledNotifications = config(
            'schedules.models.scheduledNotification.table'
        );

        $this->assertPackageTablesExist([
            $schedules,
            $types,
            $typeNotifications,
            $scheduledNotifications,
        ]);

        if (Schema::hasColumn($schedules, 'user_id')) {
            throw new \RuntimeException(
                "Cannot rollback upgrade: [{$schedules}.user_id] already exists."
            );
        }

        Schema::table($schedules, function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
        });

        $this->narrowNullableForeignUuid(
            $schedules,
            'type_id',
            $types
        );
        $this->narrowNullableUuid($schedules, 'schedulable_id');

        $this->narrowNullableForeignUuid(
            $typeNotifications,
            'type_id',
            $types
        );

        $this->narrowNullableForeignUuid(
            $scheduledNotifications,
            'type_notification_id',
            $typeNotifications
        );
    }

    /**
     * @param  list<string>  $tables
     */
    protected function assertPackageTablesExist(array $tables): void
    {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                throw new \RuntimeException(
                    "Upgrade from v2.5.1 requires table [{$table}] to exist."
                );
            }
        }
    }

    protected function widenNullableForeignString(
        string $table,
        string $column,
        string $foreignTable
    ): void {
        if (! Schema::hasColumn($table, $column)) {
            throw new \RuntimeException(
                "Upgrade from v2.5.1 expected column [{$table}.{$column}]."
            );
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropForeign([$column]);
        });

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->string($column)->nullable()->change();
        });

        Schema::table($table, function (Blueprint $blueprint) use ($column, $foreignTable): void {
            $blueprint->foreign($column)->references('id')->on($foreignTable);
        });
    }

    protected function widenNullableString(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            throw new \RuntimeException(
                "Upgrade from v2.5.1 expected column [{$table}.{$column}]."
            );
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->string($column)->nullable()->change();
        });
    }

    protected function narrowNullableForeignUuid(
        string $table,
        string $column,
        string $foreignTable
    ): void {
        if (! Schema::hasColumn($table, $column)) {
            throw new \RuntimeException(
                "Rollback expected column [{$table}.{$column}]."
            );
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropForeign([$column]);
        });

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->uuid($column)->nullable()->change();
        });

        Schema::table($table, function (Blueprint $blueprint) use ($column, $foreignTable): void {
            $blueprint->foreign($column)->references('id')->on($foreignTable);
        });
    }

    protected function narrowNullableUuid(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            throw new \RuntimeException(
                "Rollback expected column [{$table}.{$column}]."
            );
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->uuid($column)->nullable()->change();
        });
    }
};
