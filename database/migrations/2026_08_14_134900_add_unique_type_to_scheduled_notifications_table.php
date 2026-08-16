<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('schedules.models.scheduledNotification.table');

        $this->assertNoDuplicatePairs($table);

        Schema::table(
            $table,
            function (Blueprint $table): void {
                $table->unique(
                    ['schedule_id', 'type_notification_id'],
                    'scheduled_notifications_schedule_type_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            config('schedules.models.scheduledNotification.table'),
            function (Blueprint $table): void {
                $table->dropUnique(
                    'scheduled_notifications_schedule_type_unique'
                );
            }
        );
    }

    protected function assertNoDuplicatePairs(string $table): void
    {
        $duplicates = DB::table($table)
            ->select('schedule_id', 'type_notification_id')
            ->selectRaw('COUNT(*) as aggregate')
            ->whereNotNull('type_notification_id')
            ->groupBy('schedule_id', 'type_notification_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $nullDuplicates = DB::table($table)
            ->select('schedule_id')
            ->selectRaw('COUNT(*) as aggregate')
            ->whereNull('type_notification_id')
            ->groupBy('schedule_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty() && $nullDuplicates->isEmpty()) {
            return;
        }

        $pairs = [];

        foreach ($duplicates as $duplicate) {
            $pairs[] = sprintf(
                'schedule_id=%s,type_notification_id=%s (count=%d)',
                $duplicate->schedule_id,
                $duplicate->type_notification_id,
                $duplicate->aggregate
            );
        }

        foreach ($nullDuplicates as $duplicate) {
            $pairs[] = sprintf(
                'schedule_id=%s,type_notification_id=null (count=%d)',
                $duplicate->schedule_id,
                $duplicate->aggregate
            );
        }

        throw new \RuntimeException(
            'Cannot add unique index scheduled_notifications_schedule_type_unique: duplicate pairs found: '
            .implode('; ', $pairs)
        );
    }
};
