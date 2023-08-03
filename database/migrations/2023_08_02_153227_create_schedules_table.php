<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSchedulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('schedules.models.type.table'), function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');

            $table->string('validity')->nullable();
            $table->string('measurement_unit_id', 16);
            $table->foreign('measurement_unit_id')->references('id')->on(config('measurementUnits.models.measurementUnit.table'));

            $table->json('roles')->nullable();
            $table->json('models')->nullable();
            $table->json('notifications')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create(config('schedules.models.schedule.table'), function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->nullableUuidMorphs('schedulable');

            $table->string('validity');
            $table->string('deadline_value');

            $table->string('measurement_unit_id', 16);
            $table->foreign('measurement_unit_id')->references('id')->on(config('measurementUnits.models.measurementUnit.table'));

            $table->datetime('expired_at')->nullable();
            $table->datetime('managed_at')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create(config('schedules.models.scheduledNotifications.table'), function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('schedule_id');
            $table->foreign('schedule_id')->references('id')->on(config('schedules.models.schedule.table'));

            $table->uuid('notification_id')->nullable();
            $table->foreign('notification_id')->references('id')->on('notifications');

            $table->string('deadline_delta_value');
            $table->string('measurement_unit');

            $table->boolean('repeatable')->nullable();

            $table->datetime('notified_at')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(config('schedules.models.scheduledNotifications.table'));
        Schema::dropIfExists(config('schedules.models.schedule.table'));
        Schema::dropIfExists(config('schedules.models.type.table'));
    }
}
