<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\TestCase;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class InvalidateSchedulesCommandTest extends TestCase
{
    use CreatesScheduleFixtures;

    /**
     * Keep this command test independent from legacy upgrade migrations: it only
     * needs the current domain schema exercised by the invalidation lifecycle.
     */
    protected function migratePackageSchema(): void
    {
        Schema::create(
            config('schedules.models.type.table'),
            function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('name');
                $table->string('validity')->nullable();
                $table->string('measurement_unit_id', 16);
                $table->boolean('allow_multiple')->nullable();
                $table->json('roles')->nullable();
                $table->json('models')->nullable();
                $table->float('percentage_validity')->nullable();
                $table->softDeletes();
                $table->timestamps();
            }
        );

        Schema::create(
            config('schedules.models.schedule.table'),
            function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type_id');
                $table->string('schedulable_type');
                $table->string('schedulable_id');
                $table->string('starting_value', 32)->nullable();
                $table->string('deadline_value', 32);
                $table->string('field')->nullable();
                $table->dateTime('expired_at')->nullable();
                $table->dateTime('managed_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
            }
        );

        Schema::create(
            config('schedules.models.typeNotification.table'),
            function (Blueprint $table): void {
                $table->string('id')->primary();
                $table->string('type_id');
                $table->string('before');
                $table->string('last_repetition')->nullable();
                $table->string('repeat_every')->nullable();
                $table->string('repeat_every_measurement_unit_id', 16)->nullable();
                $table->softDeletes();
                $table->timestamps();
            }
        );

        Schema::create(
            config('schedules.models.scheduledNotification.table'),
            function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('schedule_id');
                $table->uuid('notification_id')->nullable();
                $table->string('type_notification_id');
                $table->string('deadline_value');
                $table->string('last_dispatched_value')->nullable();
                $table->dateTime('expired_at')->nullable();
                $table->dateTime('managed_at')->nullable();
                $table->unique(
                    ['schedule_id', 'type_notification_id'],
                    'scheduled_notifications_schedule_type_unique'
                );
                $table->softDeletes();
                $table->timestamps();
            }
        );

        Schema::create(
            config('schedules.models.scheduledNotificationDispatch.table'),
            function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('scheduled_notification_id');
                $table->uuid('notification_id');
                $table->string('current_value');
                $table->dateTime('dispatched_at');
                $table->timestamps();
            }
        );
    }

    public function testInvalidatesEveryScheduleOfSelectedType(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $targetType = $this->createType($measurementUnit);
        $otherType = $this->createType($measurementUnit, [
            'id' => 'other',
            'name' => 'Other',
        ]);
        $current = $this->createSchedule($targetType);
        $pending = $this->createScheduledNotification($current, 80);
        $dispatched = $this->createScheduledNotification($current, 90);
        $dispatched->notification_id = $this->insertNotificationRow('dispatched-notification');
        $dispatched->save();

        $alreadyExpired = $this->createSchedule($targetType);
        $alreadyExpired->forceFill(['expired_at' => now()])->save();
        $managed = $this->createSchedule($targetType);
        $managed->forceFill(['managed_at' => now()])->save();
        $other = $this->createSchedule($otherType);

        $this->assertSame(Command::SUCCESS, Artisan::call(
            'schedules:invalidate',
            ['type' => $targetType->getKey(), '--chunk' => 1]
        ));

        $this->assertTrue($current->fresh()->trashed());
        $this->assertNull($current->fresh()->expired_at);
        $this->assertTrue($pending->fresh()->trashed());
        $this->assertNull($pending->fresh()->expired_at);
        $this->assertTrue($dispatched->fresh()->trashed());
        $this->assertNull($dispatched->fresh()->expired_at);
        $this->assertTrue($alreadyExpired->fresh()->trashed());
        $this->assertTrue($managed->fresh()->trashed());
        $this->assertFalse($other->fresh()->trashed());

        $output = Artisan::output();
        $this->assertStringContainsString('Schedule Type: Maintenance [maintenance]', $output);
        $this->assertStringContainsString('Schedules found: 3', $output);
        $this->assertStringContainsString('Schedules invalidated: 3', $output);
        $this->assertStringContainsString('Errors: 0', $output);
    }

    public function testDryRunDoesNotInvalidateSchedules(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $schedule = $this->createSchedule($type);

        $this->assertSame(Command::SUCCESS, Artisan::call(
            'schedules:invalidate',
            ['type' => $type->getKey(), '--dry-run' => true]
        ));

        $this->assertFalse($schedule->fresh()->trashed());
        $this->assertNull($schedule->fresh()->expired_at);
        $output = Artisan::output();
        $this->assertStringContainsString('Dry-run summary', $output);
        $this->assertStringContainsString('Schedules found: 1', $output);
        $this->assertStringContainsString('Schedules invalidated: 0', $output);
    }

    public function testAllInvalidatesEveryScheduleOfEveryType(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $firstType = $this->createType($measurementUnit);
        $secondType = $this->createType($measurementUnit, [
            'id' => 'other',
            'name' => 'Other',
        ]);
        $first = $this->createSchedule($firstType);
        $second = $this->createSchedule($secondType);
        $alreadyExpired = $this->createSchedule($secondType);
        $alreadyExpired->forceFill(['expired_at' => now()])->save();
        $managed = $this->createSchedule($firstType);
        $managed->forceFill(['managed_at' => now()])->save();

        $this->assertSame(Command::SUCCESS, Artisan::call(
            'schedules:invalidate',
            ['--all' => true, '--chunk' => 1]
        ));

        $this->assertTrue($first->fresh()->trashed());
        $this->assertTrue($second->fresh()->trashed());
        $this->assertTrue($alreadyExpired->fresh()->trashed());
        $this->assertTrue($managed->fresh()->trashed());

        $output = Artisan::output();
        $this->assertStringContainsString('Schedule Types: all', $output);
        $this->assertStringContainsString('Schedules found: 4', $output);
        $this->assertStringContainsString('Schedules invalidated: 4', $output);
        $this->assertStringContainsString('Errors: 0', $output);
    }

    public function testRequiresEitherTypeOrAll(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Either a Schedule Type ID or the --all option is required'
        );

        Artisan::call('schedules:invalidate');
    }

    public function testRejectsTypeTogetherWithAll(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The type argument and --all option cannot be used together'
        );

        Artisan::call('schedules:invalidate', [
            'type' => $type->getKey(),
            '--all' => true,
        ]);
    }

    public function testRepeatedExecutionIsIdempotent(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $schedule = $this->createSchedule($type);

        Artisan::call('schedules:invalidate', ['type' => $type->getKey()]);
        $invalidatedAt = $schedule->fresh()->deleted_at;

        $this->assertSame(Command::SUCCESS, Artisan::call(
            'schedules:invalidate',
            ['type' => $type->getKey()]
        ));

        $this->assertEquals($invalidatedAt, $schedule->fresh()->deleted_at);
        $this->assertStringContainsString('Schedules found: 0', Artisan::output());
    }

    public function testUnknownTypeFailsClearly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Schedule Type [missing] does not exist');

        Artisan::call('schedules:invalidate', ['type' => 'missing']);
    }

    public function testInvalidChunkSizeFailsClearly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The chunk option must be greater than zero');

        Artisan::call('schedules:invalidate', [
            'type' => 'missing',
            '--chunk' => 0,
        ]);
    }

    protected function createSchedule(Type $type): Schedule
    {
        $schedulable = $this->createSchedulable();

        return Schedule::query()->forceCreate([
            'type_id' => $type->getKey(),
            'schedulable_type' => $schedulable->getMorphClass(),
            'schedulable_id' => $schedulable->getKey(),
            'starting_value' => 0,
            'deadline_value' => 100,
        ]);
    }
}
