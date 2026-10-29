<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Duty\Service;

use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyPoint;
use App\Logic\Rental\Sauna\Duty\Manager\SaunaDutyManagerInterface;
use App\Logic\Rental\Sauna\Duty\Manager\SaunaDutyWaiverManagerInterface;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDuty;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyWaiver;
use App\Logic\Rental\Sauna\Duty\Service\SaunaDutyRoster;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaClosure;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\Model\Weekday;
use PHPUnit\Framework\TestCase;

final class SaunaDutyRosterTest extends TestCase
{
    private const string SATURDAY = '2026-10-10';

    /**
     * Raster 15–23 Uhr in 2-Stunden-Einheiten: Eine 4-Stunden-Buchung ab 15:00 braucht um 15:00 den
     * Einlass, 17:00 „läuft“; um 19:00 folgt nahtlos eine offene Anfrage (Übergang, wegen der
     * angenommenen Buchung zwingend), die um 21:00 endet (Abschließen, nur angefragt).
     */
    public function testDerivesEntryTransitionAndClosingPointsFromSeasonAndBookings(): void
    {
        $roster = $this->roster(
            [
                $this->booking('long', '15:00', '19:00', SaunaBookingStatus::Accepted),
                $this->booking('open', '19:00', '21:00', SaunaBookingStatus::Open),
                $this->booking('rejected', '21:00', '23:00', SaunaBookingStatus::Rejected),
            ],
            [$this->duty('duty-1', '15:00')],
        );

        $points = $roster->pointsOn(new \DateTimeImmutable(self::SATURDAY));

        self::assertSame(
            ['15:00 required entry', '17:00 running none', '19:00 required transition', '21:00 requested closing', '23:00 free none'],
            array_map(self::describe(...), $points),
        );
        self::assertSame('Vorstand', $points[0]->duties[0]->lastName);
        self::assertSame('long', $points[1]->runningBookings[0]->id);
    }

    public function testAddsBookingTimesOutsideTheGridAndKeepsOrphanedDuties(): void
    {
        $roster = $this->roster(
            [$this->booking('individual', '16:30', '18:30', SaunaBookingStatus::Accepted)],
            [$this->duty('orphan', '12:00')],
        );

        $points = $roster->pointsOn(new \DateTimeImmutable(self::SATURDAY));

        self::assertSame(
            ['12:00 unscheduled none', '15:00 free none', '16:30 required entry', '17:00 running none', '18:30 required closing', '19:00 free none', '21:00 free none', '23:00 free none'],
            array_map(self::describe(...), $points),
        );
    }

    public function testWaiverForASingleTimeMarksOnlyThatPointAndKeepsItsState(): void
    {
        $roster = $this->roster(
            [$this->booking('long', '15:00', '17:00', SaunaBookingStatus::Accepted)],
            [],
            [new SaunaDutyWaiver('waiver-1', new \DateTimeImmutable(self::SATURDAY), '15:00')],
        );

        $day = $roster->days(new \DateTimeImmutable(self::SATURDAY), 1)[0];

        self::assertNull($day->waiverId);
        self::assertSame('15:00 required entry', self::describe($day->points[0]));
        self::assertSame('waiver-1', $day->points[0]->waiverId);
        self::assertTrue($day->points[0]->dutyWaived);
        self::assertFalse($day->points[1]->dutyWaived);
    }

    public function testWholeDayWaiverMarksEveryScheduledPointButNotOrphanedDuties(): void
    {
        $roster = $this->roster(
            [$this->booking('long', '15:00', '17:00', SaunaBookingStatus::Accepted)],
            [$this->duty('orphan', '12:00')],
            [new SaunaDutyWaiver('day-waiver', new \DateTimeImmutable(self::SATURDAY), null)],
        );

        $day = $roster->days(new \DateTimeImmutable(self::SATURDAY), 1)[0];

        self::assertSame('day-waiver', $day->waiverId);
        self::assertFalse($day->points[0]->dutyWaived, 'Der verwaiste Dienst um 12:00 ist kein Dienstzeitpunkt.');
        self::assertSame([true], array_values(array_unique(array_map(
            static fn (SaunaDutyPoint $point): bool => $point->dutyWaived,
            array_slice($day->points, 1),
        ))));
        self::assertNull($day->points[1]->waiverId);
    }

    public function testClosureDaysHaveNoEntryPoints(): void
    {
        $days = $this->roster([], [])->days(new \DateTimeImmutable('2026-10-17'), 1);

        self::assertTrue($days[0]->closed);
        self::assertSame('Revision', $days[0]->closedReason);
        self::assertSame([], $days[0]->points);
    }

    /**
     * @param list<SaunaBooking> $bookings
     * @param list<SaunaDuty>       $duties
     * @param list<SaunaDutyWaiver> $waivers
     */
    private function roster(array $bookings, array $duties, array $waivers = []): SaunaDutyRoster
    {
        $now = new \DateTimeImmutable('2026-09-01T00:00:00');
        $seasons = $this->createStub(SaunaSeasonManagerInterface::class);
        $seasons->method('all')->willReturn([new SaunaSeason(
            id: 'season-1',
            startsOn: new \DateTimeImmutable('2026-10-01'),
            endsOn: null,
            slotDurationMinutes: 120,
            openingHours: [new SaunaOpeningHours(Weekday::Saturday, '15:00', '23:00')],
            createdAt: $now,
            updatedAt: $now,
            closures: [new SaunaClosure(new \DateTimeImmutable('2026-10-17'), new \DateTimeImmutable('2026-10-17'), 'Revision')],
        )]);
        $bookingManager = $this->createStub(SaunaBookingManagerInterface::class);
        $bookingManager->method('findBetween')->willReturn($bookings);
        $dutyManager = $this->createStub(SaunaDutyManagerInterface::class);
        $dutyManager->method('findBetween')->willReturn($duties);

        $waiverManager = $this->createStub(SaunaDutyWaiverManagerInterface::class);
        $waiverManager->method('findBetween')->willReturn($waivers);

        return new SaunaDutyRoster($seasons, $bookingManager, $dutyManager, $waiverManager);
    }

    private static function describe(SaunaDutyPoint $point): string
    {
        return $point->time.' '.$point->state->value.' '.$point->kind->value;
    }

    private function duty(string $id, string $time): SaunaDuty
    {
        return new SaunaDuty($id, new \DateTimeImmutable(self::SATURDAY), $time, 'member-1', '1001', 'Vera', 'Vorstand');
    }

    private function booking(string $id, string $startTime, string $endTime, SaunaBookingStatus $status): SaunaBooking
    {
        $submittedAt = new \DateTimeImmutable('2026-09-20T10:00:00');

        return new SaunaBooking(
            id: $id,
            date: new \DateTimeImmutable(self::SATURDAY),
            startTime: $startTime,
            endTime: $endTime,
            personCount: 4,
            priceCents: 2000,
            firstName: 'Erika',
            lastName: 'Musterfrau',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            email: null,
            message: '',
            status: $status,
            memberId: null,
            memberNumber: null,
            submittedAt: $submittedAt,
            updatedAt: $submittedAt,
        );
    }
}
