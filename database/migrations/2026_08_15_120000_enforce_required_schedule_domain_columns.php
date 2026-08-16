<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $checks = [
            config('schedules.models.schedule.table') => [
                'type_id',
                'schedulable_type',
                'schedulable_id',
            ],
            config('schedules.models.typeNotification.table') => [
                'type_id',
                'before',
            ],
            config('schedules.models.scheduledNotification.table') => [
                'type_notification_id',
            ],
        ];

        $this->assertNoNullIds($checks);

        foreach ($checks as $table => $columns) {
            $this->makeNotNullable($table, $columns);
        }
    }

    public function down(): void
    {
        $this->makeNullable(
            config('schedules.models.schedule.table'),
            ['type_id', 'schedulable_type', 'schedulable_id']
        );
        $this->makeNullable(
            config('schedules.models.typeNotification.table'),
            ['type_id', 'before']
        );
        $this->makeNullable(
            config('schedules.models.scheduledNotification.table'),
            ['type_notification_id']
        );
    }

    /**
     * @param  array<string, list<string>>  $checks
     */
    protected function assertNoNullIds(array $checks): void
    {
        $messages = [];

        foreach ($checks as $table => $columns) {
            $ids = DB::table($table)
                ->where(function ($query) use ($columns): void {
                    foreach ($columns as $column) {
                        $query->orWhereNull($column);
                    }
                })
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if ($ids === []) {
                continue;
            }

            $messages[] = "[{$table}] ("
                .implode(', ', $columns)
                .'): '
                .implode(', ', $ids);
        }

        if ($messages === []) {
            return;
        }

        throw new \RuntimeException(
            'Cannot enforce NOT NULL on required schedule domain columns: null values found for ids: '
            .implode('; ', $messages)
        );
    }

    /**
     * @param  list<string>  $columns
     */
    protected function makeNotNullable(string $table, array $columns): void
    {
        $nullableColumns = [];

        foreach ($columns as $column) {
            if ($this->columnIsNullable($table, $column)) {
                $nullableColumns[] = $column;
            }
        }

        if ($nullableColumns === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($nullableColumns): void {
            foreach ($nullableColumns as $column) {
                $blueprint->string($column)->nullable(false)->change();
            }
        });
    }

    /**
     * @param  list<string>  $columns
     */
    protected function makeNullable(string $table, array $columns): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
            foreach ($columns as $column) {
                $blueprint->string($column)->nullable()->change();
            }
        });
    }

    protected function columnIsNullable(string $table, string $column): bool
    {
        foreach (Schema::getColumns($table) as $definition) {
            if (($definition['name'] ?? null) === $column) {
                return (bool) ($definition['nullable'] ?? false);
            }
        }

        throw new \RuntimeException(
            "Cannot enforce nullability: column [{$table}.{$column}] was not found."
        );
    }
};
