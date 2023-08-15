<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSchedulesNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
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
    }
}
