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

            $table->string('type_id');
            $table->foreign('type_id')->references('id')->on(config('schedules.models.type.table'));

            $table->string('schedulable_type');
            $table->string('schedulable_id');
            $table->index(['schedulable_type', 'schedulable_id']);

            $table->string('starting_value', 32)->nullable();
            $table->string('deadline_value', 32);

            $table->string('field')->nullable();

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
