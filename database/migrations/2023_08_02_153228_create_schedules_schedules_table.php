<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSchedulesSchedulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(config('schedules.models.schedule.table'), function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users');

            $table->uuid('type_id')->nullable();
            $table->foreign('type_id')->references('id')->on(config('schedules.models.type.table'));

            $table->nullableUuidMorphs('schedulable');

            $table->string('validity');
            $table->string('deadline_value');

            $table->string('field')->nullable();

            $table->string('measurement_unit_id', 16);
            $table->foreign('measurement_unit_id')->references('id')->on(config('measurementUnits.models.measurementUnit.table'));

            $table->datetime('expired_at')->nullable();
            $table->datetime('managed_at')->nullable();

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
        Schema::dropIfExists(config('schedules.models.schedule.table'));
    }
}
