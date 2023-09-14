<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSchedulableTypeNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('schedules.models.typeNotification.table'), function (Blueprint $table) {
            $table->string('id')->primary();

            $table->uuid('type_id')->nullable();
            $table->foreign('type_id')->references('id')->on(config('schedules.models.type.table'));

            $table->string('before')->nullable();
            $table->unsignedSmallInteger('urgency')->nullable();

            $table->string('repeat_every')->nullable();
            $table->string('repeat_every_measurement_unit_id', 16)->nullable();
            $table->foreign('repeat_every_measurement_unit_id', 'type_notification_measurement_unit_id')->references('id')->on(config('measurementUnits.models.measurementUnit.table'));

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
        Schema::dropIfExists(config('schedules.models.typeNotification.table'));
    }
}
