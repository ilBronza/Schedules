<?php

namespace IlBronza\Schedules\Tests\Feature;

use IlBronza\Schedules\Helpers\Applicators\ScheduleApplicatorHelper;
use IlBronza\Schedules\Models\Schedule;
use IlBronza\Schedules\Models\Type;
use IlBronza\Schedules\Tests\Concerns\CreatesScheduleFixtures;
use IlBronza\Schedules\Tests\Fixtures\Models\Alpha\CollidingSchedulable as AlphaCollidingSchedulable;
use IlBronza\Schedules\Tests\Fixtures\Models\Beta\CollidingSchedulable as BetaCollidingSchedulable;
use IlBronza\Schedules\Tests\Fixtures\Models\Schedulable;
use IlBronza\Schedules\Tests\TestCase;
use RuntimeException;

class SilentFallbacksTest extends TestCase
{
    use CreatesScheduleFixtures;

    public function testGetStartingValueDoesNotSwallowMeasurementUnitValidationErrors(): void
    {
        $type = $this->createType($this->createMeasurementUnit());
        $schedulable = $this->createSchedulable();

        $schedule = Schedule::query()->forceCreate([
            'type_id' => $type->getKey(),
            'schedulable_type' => $schedulable->getMorphClass(),
            'schedulable_id' => $schedulable->getKey(),
            'starting_value' => 'not-a-number',
            'deadline_value' => 100,
        ]);
        $schedule->setRelation('type', $type);

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(9901);
        $this->expectExceptionMessageMatches('/not-a-number/');

        $schedule->getStartingValue();
    }

    public function testMalformedNotificationRolesRaiseContextualException(): void
    {
        $type = new Type();
        $type->setRawAttributes([
            'id' => 'type-roles',
            'roles' => json_encode([
                ['roles' => 4],
                ['unexpected' => true],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/type-roles.*roles.*unexpected/s'
        );

        $type->getNotificationRoleIds();
    }

    public function testNullNotificationRoleEntriesRaiseContextualException(): void
    {
        $type = new Type();
        $type->setRawAttributes([
            'id' => 'type-null-role',
            'roles' => json_encode([4, null]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/type-null-role.*roles/');

        $type->getNotificationRoleIds();
    }

    public function testMissingRequiredScopeDoesNotReturnUnscopedQuery(): void
    {
        $type = $this->createType($this->createMeasurementUnit(), [
            'name' => 'Missing Scope Type',
        ]);
        $schedulable = $this->createSchedulable();
        $schedulable->setApplicatingScheduleType($type);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/'.$type->getKey()
            .'.*scopeMissingScopeType.*'
            .preg_quote(Schedulable::class, '/')
            .'/s'
        );

        $schedulable->getSchedulableElementsQuery();
    }

    public function testSameBasenameDifferentNamespaceDoesNotMatchConfiguredModel(): void
    {
        $type = $this->createType(
            $this->createMeasurementUnit(),
            [
                'models' => [
                    [
                        'model' => AlphaCollidingSchedulable::class,
                        'source' => 'current_reading',
                    ],
                ],
            ]
        );

        $sameBasenameDifferentNamespace = BetaCollidingSchedulable::query()->create([
            'name' => 'Beta vehicle',
            'current_reading' => 55,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Model '.BetaCollidingSchedulable::class
            .' not available for schedule '.$type->getName()
        );

        ScheduleApplicatorHelper::applicateScheduleToModel(
            $type,
            $sameBasenameDifferentNamespace
        );
    }
}
