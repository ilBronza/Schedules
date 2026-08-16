<?php

namespace IlBronza\Schedules\Tests\Unit;

use IlBronza\Schedules\Models\Type;
use PHPUnit\Framework\TestCase;

class TypeTest extends TestCase
{
    public function testNotificationRoleIdsAreExtractedAndDeduplicated(): void
    {
        $type = new Type();
        $type->setRawAttributes([
            'roles' => json_encode([
                ['roles' => 4],
                ['role_id' => 7],
                ['id' => 4],
            ]),
        ]);

        $this->assertSame(
            [4, 7],
            $type->getNotificationRoleIds()->all()
        );
    }

    public function testPercentageValidityLimitUsesConfiguredValue(): void
    {
        $type = new Type();
        $type->percentage_validity = 85;

        $this->assertSame(85.0, $type->getPercentageValidityLimit());
    }

    public function testPercentageValidityLimitHasDefault(): void
    {
        $this->assertSame(
            99.0,
            (new Type())->getPercentageValidityLimit()
        );
    }
}
