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

    private function booking(?string $email = null): SaunaBooking
    {
        $submittedAt = new \DateTimeImmutable('2026-09-25T10:00:00');

        return new SaunaBooking(
            id: 'booking-1',
            date: new \DateTimeImmutable('2026-10-05'),
            startTime: '18:00',
            endTime: '20:00',
            personCount: 4,
            priceCents: 2000,
            firstName: 'Erika',
            lastName: 'Musterfrau',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            email: $email,
            message: '',
            status: SaunaBookingStatus::Open,
            memberId: null,
            memberNumber: null,
            submittedAt: $submittedAt,
            updatedAt: $submittedAt,
        );
    }
}
