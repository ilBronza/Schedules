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
        Schema::create(config('schedules.models.scheduledNotification.table'), function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('schedule_id');
            $table->foreign('schedule_id')->references('id')->on(config('schedules.models.schedule.table'));

            $table->uuid('notification_id')->nullable();
            $table->foreign('notification_id')->references('id')->on('notifications');

            $table->uuid('type_notification_id')->nullable();
            $table->foreign('type_notification_id')->references('id')->on(config('schedules.models.typeNotification.table'));

            $table->string('deadline_value');

            $table->datetime('expired_at')->nullable();

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
        Schema::dropIfExists(config('schedules.models.scheduledNotification.table'));
    }
}
