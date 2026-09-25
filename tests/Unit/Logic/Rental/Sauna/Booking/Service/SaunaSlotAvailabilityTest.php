<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Booking\Service;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\Model\SaunaSlotState;
use App\Logic\Rental\Sauna\Booking\Service\SaunaSlotAvailability;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\Model\Weekday;
use PHPUnit\Framework\TestCase;

final class SaunaSlotAvailabilityTest extends TestCase
{
    private const string MONDAY = '2026-10-05';

    public function testCalendarMarksFreeBookedAndPastSlots(): void
    {
        $availability = $this->availability([
            $this->booking('open', '18:00', '19:00', SaunaBookingStatus::Open),
            $this->booking('rejected', '19:00', '20:00', SaunaBookingStatus::Rejected),
        ]);

        $days = $availability->calendar(
            new \DateTimeImmutable(self::MONDAY),
            2,
            new \DateTimeImmutable(self::MONDAY.'T17:30:00'),
        );

        self::assertCount(2, $days);
        self::assertSame('Wintersaison', $days[0]->seasonName);
        $states = array_map(static fn ($slot): string => $slot->startTime.' '.$slot->state->value, $days[0]->slots);
        self::assertSame(['17:00 past', '18:00 booked', '19:00 free', '20:00 free'], $states);
        self::assertSame([], $days[1]->slots);
    }

    public function testRejectsRangeOverlappingAnOpenRequest(): void
    {
        $availability = $this->availability([$this->booking('open', '18:00', '19:00', SaunaBookingStatus::Open)]);

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('bereits belegt');
        $availability->assertBookable(new \DateTimeImmutable(self::MONDAY), '18:00', '20:00', new \DateTimeImmutable('2026-09-25T10:00:00'));
    }

    public function testAllowsRangeWhenOnlyRejectedBookingsOverlap(): void
    {
        $availability = $this->availability([$this->booking('rejected', '18:00', '19:00', SaunaBookingStatus::Rejected)]);

        $availability->assertBookable(new \DateTimeImmutable(self::MONDAY), '18:00', '20:00', new \DateTimeImmutable('2026-09-25T10:00:00'));

        $this->addToAssertionCount(1);
    }

    public function testRejectsSlotsInThePast(): void
    {
        $availability = $this->availability([]);

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Vergangene');
        $availability->assertBookable(new \DateTimeImmutable(self::MONDAY), '17:00', '18:00', new \DateTimeImmutable(self::MONDAY.'T17:00:00'));
    }

    public function testRejectsRangeNotAlignedToSlots(): void
    {
        $availability = $this->availability([]);

        $this->expectException(BusinessRuleViolationException::class);
        $availability->assertBookable(new \DateTimeImmutable(self::MONDAY), '17:30', '18:30', new \DateTimeImmutable('2026-09-25T10:00:00'));
    }

    /**
     * @param list<SaunaBooking> $bookings
     */
    private function availability(array $bookings): SaunaSlotAvailability
    {
        $now = new \DateTimeImmutable('2026-09-01T00:00:00');
        $season = new SaunaSeason(
            id: 'season-1',
            name: 'Wintersaison',
            startsOn: new \DateTimeImmutable('2026-10-01'),
            endsOn: null,
            slotDurationMinutes: 60,
            openingHours: [new SaunaOpeningHours(Weekday::Monday, '17:00', '21:00')],
            createdAt: $now,
            updatedAt: $now,
        );
        $seasons = $this->createStub(SaunaSeasonManagerInterface::class);
        $seasons->method('all')->willReturn([$season]);
        $seasons->method('findCovering')->willReturnCallback(
            static fn (\DateTimeImmutable $date): ?SaunaSeason => $season->covers($date) ? $season : null,
        );
        $bookingManager = $this->createStub(SaunaBookingManagerInterface::class);
        $bookingManager->method('findBetween')->willReturn($bookings);

        return new SaunaSlotAvailability($seasons, $bookingManager);
    }

    private function booking(string $id, string $startTime, string $endTime, SaunaBookingStatus $status): SaunaBooking
    {
        $submittedAt = new \DateTimeImmutable('2026-09-20T10:00:00');

        return new SaunaBooking(
            id: $id,
            date: new \DateTimeImmutable(self::MONDAY),
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
