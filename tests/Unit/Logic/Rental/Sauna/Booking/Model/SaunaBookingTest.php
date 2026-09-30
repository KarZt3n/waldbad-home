<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Booking\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use PHPUnit\Framework\TestCase;

final class SaunaBookingTest extends TestCase
{
    public function testOpenBookingCanBeAcceptedAndLaterRejected(): void
    {
        $booking = $this->booking();
        $accepted = $booking->accept(new \DateTimeImmutable('2026-09-26T09:00:00'));
        $rejected = $accepted->reject(new \DateTimeImmutable('2026-09-27T09:00:00'));

        self::assertSame(SaunaBookingStatus::Accepted, $accepted->status);
        self::assertSame(SaunaBookingStatus::Rejected, $rejected->status);
        self::assertTrue($accepted->blocksTime());
        self::assertFalse($rejected->blocksTime());
        self::assertSame('2026-09-27T09:00:00', $rejected->updatedAt->format('Y-m-d\TH:i:s'));
    }

    public function testAcceptingTwiceIsRejected(): void
    {
        $accepted = $this->booking()->accept(new \DateTimeImmutable('2026-09-26T09:00:00'));

        $this->expectException(BusinessRuleViolationException::class);
        $accepted->accept(new \DateTimeImmutable('2026-09-26T10:00:00'));
    }

    public function testOnlyAcceptedBookingsCanBeCancelledAndThenFreeTheirTime(): void
    {
        $cancelled = $this->booking()->accept(new \DateTimeImmutable('2026-09-26T09:00:00'))->cancel(new \DateTimeImmutable('2026-09-27T09:00:00'));

        self::assertSame(SaunaBookingStatus::Cancelled, $cancelled->status);
        self::assertFalse($cancelled->blocksTime());

        $this->expectException(BusinessRuleViolationException::class);
        $this->booking()->cancel(new \DateTimeImmutable('2026-09-27T09:00:00'));
    }

    public function testOverlapIsCheckedPerDayAndTimeRange(): void
    {
        $booking = $this->booking();
        $day = new \DateTimeImmutable('2026-10-05');

        self::assertTrue($booking->overlaps($day, '18:30', '19:30'));
        self::assertFalse($booking->overlaps($day, '20:00', '21:00'));
        self::assertFalse($booking->overlaps($day, '17:00', '18:00'));
        self::assertFalse($booking->overlaps(new \DateTimeImmutable('2026-10-06'), '18:00', '20:00'));
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(BusinessRuleViolationException::class);

        $this->booking(email: 'keine-adresse');
    }

    public function testRequesterKeyGroupsByMemberOtherwiseByNameAndBirthDate(): void
    {
        $guest = $this->booking();

        self::assertSame($guest->requesterKey(), $this->booking(firstName: ' erika ', lastName: 'MUSTERFRAU', id: 'booking-2')->requesterKey());
        self::assertNotSame($guest->requesterKey(), $this->booking(birthDate: '1991-01-01')->requesterKey());
        self::assertSame(
            $this->booking(memberId: 'member-7')->requesterKey(),
            $this->booking(firstName: 'Eri', memberId: 'member-7')->requesterKey(),
        );
        self::assertNotSame($guest->requesterKey(), $this->booking(memberId: 'member-7')->requesterKey());
    }

    private function booking(
        ?string $email = null,
        string $firstName = 'Erika',
        string $lastName = 'Musterfrau',
        string $birthDate = '1990-01-01',
        ?string $memberId = null,
        string $id = 'booking-1',
    ): SaunaBooking {
        $submittedAt = new \DateTimeImmutable('2026-09-25T10:00:00');

        return new SaunaBooking(
            id: $id,
            date: new \DateTimeImmutable('2026-10-05'),
            startTime: '18:00',
            endTime: '20:00',
            personCount: 4,
            priceCents: 2000,
            firstName: $firstName,
            lastName: $lastName,
            birthDate: new \DateTimeImmutable($birthDate),
            email: $email,
            message: '',
            status: SaunaBookingStatus::Open,
            memberId: $memberId,
            memberNumber: null,
            submittedAt: $submittedAt,
            updatedAt: $submittedAt,
        );
    }
}
