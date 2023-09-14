<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSchedulesTypesTable extends Migration
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

            $table->boolean('allow_multiple')->nullable();

            $table->json('roles')->nullable();
            $table->json('models')->nullable();

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
        Schema::dropIfExists(config('schedules.models.type.table'));
    }
}
