<?php

namespace App\Tests\Unit\Logic\Event\Activity\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Event\Activity\Model\EventActivity;
use PHPUnit\Framework\TestCase;

final class EventActivityTest extends TestCase
{
    public function testDefaultRequiredHelpersIsOptional(): void
    {
        $now = new \DateTimeImmutable();
        $activity = new EventActivity(
            id: 'activity-1', name: 'Aufbau', description: '', active: true,
            defaultRequiredHelpers: null, alwaysIncluded: false, createdAt: $now, updatedAt: $now,
        );

        self::assertNull($activity->defaultRequiredHelpers);
        self::assertFalse($activity->alwaysIncluded);
    }

    public function testDefaultRequiredHelpersMustBeWithinRange(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('zwischen 1 und 999');

        $now = new \DateTimeImmutable();
        new EventActivity(
            id: 'activity-2', name: 'Aufbau', description: '', active: true,
            defaultRequiredHelpers: 0, alwaysIncluded: false, createdAt: $now, updatedAt: $now,
        );
    }

    public function testUpdateCanChangeTheDefaultAndAlwaysIncludedFlag(): void
    {
        $now = new \DateTimeImmutable();
        $activity = new EventActivity(
            id: 'activity-3', name: 'Aufbau', description: '', active: true,
            defaultRequiredHelpers: 3, alwaysIncluded: false, createdAt: $now, updatedAt: $now,
        );

        $updated = $activity->update('Aufbau', '', true, 7, true, $now);

        self::assertSame(7, $updated->defaultRequiredHelpers);
        self::assertTrue($updated->alwaysIncluded);
    }
}
