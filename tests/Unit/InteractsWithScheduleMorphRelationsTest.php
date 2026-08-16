<?php

namespace IlBronza\Schedules\Tests\Unit;

use IlBronza\Schedules\Contracts\Schedulable as SchedulableContract;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\ScheduledNotification;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\TestCase;
use IlBronza\Schedules\Traits\InteractsWithSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InteractsWithScheduleMorphRelationsTest extends TestCase
{
    use CreatesScheduleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('schedulables_b', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->float('current_reading');
            $table->timestamps();
        });

        Relation::enforceMorphMap([
            'schedulable_a' => MorphSchedulableA::class,
            'schedulable_b' => MorphSchedulableB::class,
        ]);
    }

    protected function tearDown(): void
    {
        Relation::morphMap([], false);
        Relation::requireMorphMap(false);

        parent::tearDown();
    }

    public function testSchedulesAndNotificationsAreScopedByMorphTypeWithSharedIds(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $typeA = $this->createType($measurementUnit, [
            'id' => 'type-a',
            'name' => 'Type A',
        ]);
        $typeB = $this->createType($measurementUnit, [
            'id' => 'type-b',
            'name' => 'Type B',
        ]);

        $modelA = MorphSchedulableA::query()->create([
            'name' => 'A',
            'current_reading' => 10,
        ]);
        $modelB = MorphSchedulableB::query()->create([
            'name' => 'B',
            'current_reading' => 20,
        ]);

        $this->assertSame($modelA->getKey(), $modelB->getKey());
        $this->assertSame('schedulable_a', $modelA->getMorphClass());
        $this->assertSame('schedulable_b', $modelB->getMorphClass());

        $scheduleA = Schedule::query()->forceCreate([
            'type_id' => $typeA->getKey(),
            'schedulable_type' => $modelA->getMorphClass(),
            'schedulable_id' => $modelA->getKey(),
            'starting_value' => 0,
            'deadline_value' => 100,
        ]);
        $scheduleB = Schedule::query()->forceCreate([
            'type_id' => $typeB->getKey(),
            'schedulable_type' => $modelB->getMorphClass(),
            'schedulable_id' => $modelB->getKey(),
            'starting_value' => 0,
            'deadline_value' => 200,
        ]);

        $notificationA = $this->createScheduledNotification(
            $scheduleA,
            50,
            $typeA
        );
        $notificationB = $this->createScheduledNotification(
            $scheduleB,
            150,
            $typeB
        );

        $this->assertEqualsCanonicalizing(
            [$scheduleA->getKey()],
            $modelA->schedules()->pluck('id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$scheduleB->getKey()],
            $modelB->schedules()->pluck('id')->all()
        );

        $this->assertEqualsCanonicalizing(
            [$notificationA->getKey()],
            $modelA->scheduledNotifications()->get()->modelKeys()
        );
        $this->assertEqualsCanonicalizing(
            [$notificationB->getKey()],
            $modelB->scheduledNotifications()->get()->modelKeys()
        );

        $this->assertEqualsCanonicalizing(
            [$typeA->getKey()],
            $modelA->scheduleTypes()->pluck(
                config('schedules.models.type.table') . '.id'
            )->all()
        );
        $this->assertEqualsCanonicalizing(
            [$typeB->getKey()],
            $modelB->scheduleTypes()->pluck(
                config('schedules.models.type.table') . '.id'
            )->all()
        );
    }

    public function testScheduleTypesUsesConfiguredTypeModel(): void
    {
        config([
            'schedules.models.type.class' => ConfiguredMorphType::class,
        ]);

        config([
            'schedules.models.type.class' => ConfiguredMorphType::class,
        ]);

        $type = $this->persistTypeWithoutModelValidation([
            'id' => 'configured-type',
            'name' => 'Configured',
            'models' => [],
        ]);

        $this->assertInstanceOf(ConfiguredMorphType::class, $type);

        $model = MorphSchedulableA::query()->create([
            'name' => 'A',
            'current_reading' => 10,
        ]);

        Schedule::query()->forceCreate([
            'type_id' => $type->getKey(),
            'schedulable_type' => $model->getMorphClass(),
            'schedulable_id' => $model->getKey(),
            'starting_value' => 0,
            'deadline_value' => 100,
        ]);

        $related = $model->scheduleTypes()->get();

        $this->assertInstanceOf(
            ConfiguredMorphType::class,
            $model->scheduleTypes()->getRelated()
        );
        $this->assertCount(1, $related);
        $this->assertInstanceOf(ConfiguredMorphType::class, $related->first());
        $this->assertSame($type->getKey(), $related->first()->getKey());
    }

    public function testScheduleTypesExcludesSoftDeletedSchedulesAndRespectsMorphIsolation(): void
    {
        $measurementUnit = $this->createMeasurementUnit();
        $typeA = $this->createType($measurementUnit, [
            'id' => 'type-a',
            'name' => 'Type A',
        ]);
        $typeB = $this->createType($measurementUnit, [
            'id' => 'type-b',
            'name' => 'Type B',
        ]);

        $modelA = MorphSchedulableA::query()->create([
            'name' => 'A',
            'current_reading' => 10,
        ]);
        $modelB = MorphSchedulableB::query()->create([
            'name' => 'B',
            'current_reading' => 20,
        ]);

        $this->assertSame($modelA->getKey(), $modelB->getKey());

        $scheduleA = Schedule::query()->forceCreate([
            'type_id' => $typeA->getKey(),
            'schedulable_type' => $modelA->getMorphClass(),
            'schedulable_id' => $modelA->getKey(),
            'starting_value' => 0,
            'deadline_value' => 100,
        ]);
        $scheduleB = Schedule::query()->forceCreate([
            'type_id' => $typeB->getKey(),
            'schedulable_type' => $modelB->getMorphClass(),
            'schedulable_id' => $modelB->getKey(),
            'starting_value' => 0,
            'deadline_value' => 200,
        ]);

        $typeTableId = config('schedules.models.type.table') . '.id';

        $this->assertEqualsCanonicalizing(
            [$typeA->getKey()],
            $modelA->scheduleTypes()->pluck($typeTableId)->all()
        );
        $this->assertEqualsCanonicalizing(
            [$typeB->getKey()],
            $modelB->scheduleTypes()->pluck($typeTableId)->all()
        );

        $scheduleA->delete();

        $this->assertTrue($scheduleA->fresh()->trashed());
        $this->assertSame([], $modelA->scheduleTypes()->pluck($typeTableId)->all());
        $this->assertEqualsCanonicalizing(
            [$typeB->getKey()],
            $modelB->scheduleTypes()->pluck($typeTableId)->all()
        );

        $scheduleA->restore();

        $this->assertFalse($scheduleA->fresh()->trashed());
        $this->assertEqualsCanonicalizing(
            [$typeA->getKey()],
            $modelA->scheduleTypes()->pluck($typeTableId)->all()
        );
        $this->assertEqualsCanonicalizing(
            [$typeB->getKey()],
            $modelB->scheduleTypes()->pluck($typeTableId)->all()
        );
    }

    public function testTearDownRestoresMorphMapEnforcementFlag(): void
    {
        $this->assertTrue(Relation::requiresMorphMap());

        Relation::morphMap([], false);
        Relation::requireMorphMap(false);

        $this->assertFalse(Relation::requiresMorphMap());
        $this->assertSame([], Relation::morphMap());

        $model = new class extends Model {
            protected $table = 'schedulables';
        };

        $this->assertSame($model::class, $model->getMorphClass());

        // Leave enforcement enabled so a broken tearDown cannot be masked.
        Relation::enforceMorphMap([
            'schedulable_a' => MorphSchedulableA::class,
            'schedulable_b' => MorphSchedulableB::class,
        ]);
    }
}

class MorphSchedulableA extends Model implements SchedulableContract
{
    use InteractsWithSchedule;

    protected $table = 'schedulables';

    protected $guarded = [];

    protected array $allowedScheduleValueMethods = [
        'getCurrentReading',
    ];

    public function getCurrentReading(): float
    {
        return (float) $this->current_reading;
    }
}

class MorphSchedulableB extends Model implements SchedulableContract
{
    use InteractsWithSchedule;

    protected $table = 'schedulables_b';

    protected $guarded = [];

    protected array $allowedScheduleValueMethods = [
        'getCurrentReading',
    ];

    public function getCurrentReading(): float
    {
        return (float) $this->current_reading;
    }
}

class ConfiguredMorphType extends Type
{
    public static $modelConfigPrefix = 'type';
}
