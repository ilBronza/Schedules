<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use IlBronza\Schedules\Tests\TestCase;
use RuntimeException;

class TypeApplicateClassnameControllerTest extends TestCase
{
    use CreatesScheduleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'schedules.applicableTo' => [
                Schedulable::class => 'schedulables',
            ],
        ]);
    }

    public function testGetApplicateModelIndexUrlUsesConfiguredAlias(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $schedulable = $this->createSchedulable();
        $schedulable->setApplicatingScheduleType($type);

        $url = $schedulable->getApplicateModelIndexUrl();

        $expected = route('ibSchedules.types.applicate.classname.index', [
            'type' => $type->getKey(),
            'alias' => 'schedulables',
        ]);

        $this->assertSame($expected, $url);
        $this->assertStringContainsString('/models/schedulables', $url);
        $this->assertStringNotContainsString(Schedulable::class, $url);
        $this->assertStringNotContainsString(
            rawurlencode(Schedulable::class),
            $url
        );
    }

    public function testStoreApplicatesScheduleToSelectedModels(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $first = $this->createSchedulable(currentValue: 10);
        $second = $this->createSchedulable(currentValue: 20);

        $response = $this
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.applicate.classname.store', [
                    'type' => $type->getKey(),
                    'alias' => 'schedulables',
                ]),
                ['ids' => [$first->getKey(), $second->getKey()]]
            );

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'ibaction' => 'reloadTable',
            ]);

        $this->assertSame(2, Schedule::query()->count());

        $firstSchedule = Schedule::query()
            ->where('schedulable_id', $first->getKey())
            ->first();
        $secondSchedule = Schedule::query()
            ->where('schedulable_id', $second->getKey())
            ->first();

        $this->assertNotNull($firstSchedule);
        $this->assertNotNull($secondSchedule);
        $this->assertSame(10.0, $firstSchedule->getStartingValue());
        $this->assertSame(110.0, $firstSchedule->getDeadlineValue());
        $this->assertSame(20.0, $secondSchedule->getStartingValue());
        $this->assertSame(120.0, $secondSchedule->getDeadlineValue());
    }

    public function testIndexReturnsNotFoundForMissingType(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->get(route('ibSchedules.types.applicate.classname.index', [
                'type' => '00000000-0000-0000-0000-000000000000',
                'alias' => 'schedulables',
            ]));

        $response->assertNotFound();
    }

    public function testStoreReturnsNotFoundForMissingType(): void
    {
        $schedulable = $this->createSchedulable();

        $response = $this
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.applicate.classname.store', [
                    'type' => '00000000-0000-0000-0000-000000000000',
                    'alias' => 'schedulables',
                ]),
                ['ids' => [$schedulable->getKey()]]
            );

        $response->assertNotFound();
        $this->assertSame(0, Schedule::query()->count());
    }

    public function testStoreRejectsScalarIdsWithoutReachingApplicator(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $schedulable = $this->createSchedulable();

        $response = $this
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.applicate.classname.store', [
                    'type' => $type->getKey(),
                    'alias' => 'schedulables',
                ]),
                ['ids' => $schedulable->getKey()]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids']);

        $this->assertSame(0, Schedule::query()->count());
    }

    public function testStoreRejectsEmptyIdsWithoutReachingApplicator(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $response = $this
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.applicate.classname.store', [
                    'type' => $type->getKey(),
                    'alias' => 'schedulables',
                ]),
                ['ids' => []]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids']);

        $this->assertSame(0, Schedule::query()->count());
    }

    public function testStoreRejectsDuplicateIdsWithoutReachingApplicator(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $schedulable = $this->createSchedulable();

        $response = $this
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.applicate.classname.store', [
                    'type' => $type->getKey(),
                    'alias' => 'schedulables',
                ]),
                ['ids' => [$schedulable->getKey(), $schedulable->getKey()]]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids.0', 'ids.1']);

        $this->assertSame(0, Schedule::query()->count());
    }

    public function testStoreRejectsMissingIdsWithoutReachingApplicator(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $response = $this
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.applicate.classname.store', [
                    'type' => $type->getKey(),
                    'alias' => 'schedulables',
                ]),
                ['ids' => [999999]]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids.0']);

        $this->assertSame(0, Schedule::query()->count());
    }

    public function testStoreRejectsUnknownAlias(): void
    {
        $type = $this->createType($this->createMeasurementUnit());

        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown schedule applicable alias');

        $this
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.applicate.classname.store', [
                    'type' => $type->getKey(),
                    'alias' => 'missing-alias',
                ]),
                ['ids' => [1]]
            );
    }

    public function testStoreRejectsAliasNotAllowedOnType(): void
    {
        $type = $this->persistTypeWithoutModelValidation([
            'models' => [],
        ]);
        $schedulable = $this->createSchedulable();

        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not allowed for type');

        $this
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.applicate.classname.store', [
                    'type' => $type->getKey(),
                    'alias' => 'schedulables',
                ]),
                ['ids' => [$schedulable->getKey()]]
            );
    }

    public function testStoreRejectsClassnamePassedAsAlias(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $schedulable = $this->createSchedulable();

        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown schedule applicable alias');

        $this
            ->withoutMiddleware()
            ->postJson(
                route('ibSchedules.types.applicate.classname.store', [
                    'type' => $type->getKey(),
                    'alias' => Schedulable::class,
                ]),
                ['ids' => [$schedulable->getKey()]]
            );
    }
}
