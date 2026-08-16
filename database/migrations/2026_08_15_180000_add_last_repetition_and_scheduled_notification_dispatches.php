<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $typeNotifications = config('schedules.models.typeNotification.table');
        $scheduledNotifications = config(
            'schedules.models.scheduledNotification.table'
        );
        $dispatches = config(
            'schedules.models.scheduledNotificationDispatch.table'
        );

        Schema::table($typeNotifications, function (Blueprint $table): void {
            $table->string('last_repetition')->nullable()->after('before');
        });

        Schema::table($scheduledNotifications, function (Blueprint $table): void {
            $table->string('last_dispatched_value')->nullable()->after('deadline_value');
        });

        Schema::create($dispatches, function (Blueprint $table) use ($scheduledNotifications): void {
            $table->uuid('id')->primary();
            $table->uuid('scheduled_notification_id');
            $table->foreign(
                'scheduled_notification_id',
                'scheduled_notification_dispatches_notification_fk'
            )->references('id')->on($scheduledNotifications);
            $table->uuid('notification_id');
            $table->string('current_value');
            $table->dateTime('dispatched_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            config('schedules.models.scheduledNotificationDispatch.table')
        );

        Schema::table(
            config('schedules.models.scheduledNotification.table'),
            function (Blueprint $table): void {
                $table->dropColumn('last_dispatched_value');
            }
        );

        Schema::table(
            config('schedules.models.typeNotification.table'),
            function (Blueprint $table): void {
                $table->dropColumn('last_repetition');
            }
        );
    }
};
