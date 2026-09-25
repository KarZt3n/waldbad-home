<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Season\Service;

use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\Model\Weekday;
use App\Logic\Rental\Sauna\Season\Service\SaunaSeasonRotation;
use PHPUnit\Framework\TestCase;

final class SaunaSeasonRotationTest extends TestCase
{
    private const string NOW = '2026-09-25T10:00:00';

    public function testClosesOpenSeasonAtStartOfFutureSuccessor(): void
    {
        $saved = $this->rotate(successorStartsOn: '2026-11-01');

        self::assertCount(1, $saved);
        self::assertSame('running', $saved[0]->id);
        self::assertSame('2026-11-01', $saved[0]->closedOn?->format('Y-m-d'));
        self::assertNull($saved[0]->endsOn);
    }

    public function testClosesOpenSeasonTodayWhenSuccessorAlreadyStarted(): void
    {
        $saved = $this->rotate(successorStartsOn: '2026-09-01');

        self::assertSame('2026-09-25', $saved[0]->closedOn?->format('Y-m-d'));
    }

    /** @return list<SaunaSeason> */
    private function rotate(string $successorStartsOn): array
    {
        $successor = $this->season('successor', $successorStartsOn);
        $alreadyClosed = $this->season('old', '2025-10-01')->close(new \DateTimeImmutable('2026-04-01'), new \DateTimeImmutable('2026-04-01'));
        $manager = $this->createStub(SaunaSeasonManagerInterface::class);
        $manager->method('all')->willReturn([$alreadyClosed, $this->season('running', '2026-05-01'), $successor]);
        $saved = [];
        $manager->method('save')->willReturnCallback(static function (SaunaSeason $season) use (&$saved): SaunaSeason {
            $saved[] = $season;

            return $season;
        });

        (new SaunaSeasonRotation($manager))->closeOthers($successor, new \DateTimeImmutable(self::NOW));

        return $saved;
    }

    private function season(string $id, string $startsOn): SaunaSeason
    {
        $now = new \DateTimeImmutable(self::NOW);

        return new SaunaSeason(
            id: $id,
            startsOn: new \DateTimeImmutable($startsOn),
            endsOn: null,
            slotDurationMinutes: 60,
            openingHours: [new SaunaOpeningHours(Weekday::Monday, '18:00', '21:00')],
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
