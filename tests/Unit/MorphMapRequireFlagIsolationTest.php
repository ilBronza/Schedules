<?php

namespace IlBronza\Schedules\Tests\Unit;

use IlBronza\Schedules\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Runs after InteractsWithScheduleMorphRelationsTest (filename sorts later)
 * and fails if that class left Relation::requireMorphMap() enabled.
 */
class MorphMapRequireFlagIsolationTest extends TestCase
{
    public function testMorphMapEnforcementFromPriorTestDoesNotContaminateLaterTests(): void
    {
        $this->assertFalse(Relation::requiresMorphMap());
        $this->assertSame([], Relation::morphMap());

        $model = new class extends Model {
            protected $table = 'schedulables';
        };

        $this->assertSame($model::class, $model->getMorphClass());
    }
}
