<?php

namespace App\Tests\Unit\Logic\Membership\MemberAccess\Service;

use App\Logic\Membership\MemberAccess\Service\WorkAssignmentCreditConfig;
use PHPUnit\Framework\TestCase;

final class WorkAssignmentCreditConfigTest extends TestCase
{
    private function config(): WorkAssignmentCreditConfig
    {
        return new WorkAssignmentCreditConfig(
            ['from' => '2026-01-01', 'to' => '2027-01-01'],
            // Absichtlich unsortiert übergeben — die Klasse sortiert selbst nach valid_from.
            [
                ['valid_from' => '2027-01-01', 'required_hours' => 6],
                ['valid_from' => '2026-01-01', 'required_hours' => 5],
            ],
        );
    }

    public function testExposesThePeriod(): void
    {
        $config = $this->config();

        self::assertEquals(new \DateTimeImmutable('2026-01-01'), $config->periodFrom());
        self::assertEquals(new \DateTimeImmutable('2027-01-01'), $config->periodTo());
    }

    public function testPicksTheLatestTierThatHasAlreadyBegun(): void
    {
        $config = $this->config();

        self::assertSame(5, $config->requiredHoursAt(new \DateTimeImmutable('2026-06-01')));
        self::assertSame(5, $config->requiredHoursAt((new \DateTimeImmutable('2027-01-01'))->modify('-1 day')));
        self::assertSame(6, $config->requiredHoursAt(new \DateTimeImmutable('2027-01-01')));
        self::assertSame(6, $config->requiredHoursAt(new \DateTimeImmutable('2030-01-01')));
    }

    public function testFallsBackToTheOldestTierBeforeItBegins(): void
    {
        $config = $this->config();

        self::assertSame(5, $config->requiredHoursAt(new \DateTimeImmutable('2020-01-01')));
    }

    public function testRejectsAnEmptyTierList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new WorkAssignmentCreditConfig(['from' => '2026-01-01', 'to' => '2027-01-01'], []);
    }

    public function testRejectsAPeriodWhereToIsNotAfterFrom(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new WorkAssignmentCreditConfig(
            ['from' => '2027-01-01', 'to' => '2026-01-01'],
            [['valid_from' => '2026-01-01', 'required_hours' => 5]],
        );
    }
}
