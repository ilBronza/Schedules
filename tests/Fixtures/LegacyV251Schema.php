<?php

namespace IlBronza\Schedules\Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Recreates the Schedules schema as published at tag v2.5.1.
 *
 * Historical create migrations were later edited in place; upgrades from
 * v2.5.1 never re-run those files, so tests must build this baseline
 * explicitly before applying incremental migrations.
 */
class LegacyV251Schema
{
    public static function create(): void
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

        foreach ([
            $types,
            $schedules,
            $typeNotifications,
            $scheduledNotifications,
        ] as $table) {
            if (Schema::hasTable($table)) {
                throw new RuntimeException(
                    "Legacy v2.5.1 schema expected [{$table}] to be absent before create."
                );
            }
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($measurementUnits)) {
            throw new RuntimeException(
                "Legacy v2.5.1 schema requires dependency table [{$measurementUnits}]."
            );
        }

        if (! Schema::hasTable('notifications')) {
            throw new RuntimeException(
                'Legacy v2.5.1 schema requires dependency table [notifications].'
            );
        }

        Schema::create($types, function (Blueprint $table) use ($measurementUnits): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('validity')->nullable();
            $table->string('measurement_unit_id', 16);
            $table->foreign('measurement_unit_id')
                ->references('id')
                ->on($measurementUnits);
            $table->boolean('allow_multiple')->nullable();
            $table->json('roles')->nullable();
            $table->json('models')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->float('percentage_validity')->nullable();
        });

        Schema::create($schedules, function (Blueprint $table) use ($types): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
            $table->uuid('type_id')->nullable();
            $table->foreign('type_id')->references('id')->on($types);
            $table->nullableUuidMorphs('schedulable');
            $table->string('starting_value', 32)->nullable();
            $table->string('deadline_value', 32);
            $table->string('field')->nullable();
            $table->datetime('expired_at')->nullable();
            $table->datetime('managed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create($typeNotifications, function (Blueprint $table) use ($types, $measurementUnits): void {
            $table->string('id')->primary();
            $table->uuid('type_id')->nullable();
            $table->foreign('type_id')->references('id')->on($types);
            $table->string('before')->nullable();
            $table->unsignedSmallInteger('urgency')->nullable();
            $table->string('repeat_every')->nullable();
            $table->string('repeat_every_measurement_unit_id', 16)->nullable();
            $table->foreign(
                'repeat_every_measurement_unit_id',
                'type_notification_measurement_unit_id'
            )->references('id')->on($measurementUnits);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create($scheduledNotifications, function (Blueprint $table) use ($schedules, $typeNotifications): void {
            $table->uuid('id')->primary();
            $table->uuid('schedule_id');
            $table->foreign('schedule_id')->references('id')->on($schedules);
            $table->uuid('notification_id')->nullable();
            $table->foreign('notification_id')->references('id')->on('notifications');
            $table->uuid('type_notification_id')->nullable();
            $table->foreign('type_notification_id')
                ->references('id')
                ->on($typeNotifications);
            $table->string('deadline_value');
            $table->datetime('expired_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
