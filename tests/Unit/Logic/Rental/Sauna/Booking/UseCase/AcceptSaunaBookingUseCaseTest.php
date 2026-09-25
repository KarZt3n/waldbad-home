<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\UseCase\AcceptSaunaBookingUseCase;
use PHPUnit\Framework\TestCase;

final class AcceptSaunaBookingUseCaseTest extends TestCase
{
    public function testAcceptsWhenNoOtherAcceptedBookingOverlaps(): void
    {
        $booking = $this->booking('b1', SaunaBookingStatus::Open);
        $competingOpenRequest = $this->booking('b2', SaunaBookingStatus::Open);
        $manager = $this->createMock(SaunaBookingManagerInterface::class);
        $manager->method('get')->willReturn($booking);
        $manager->method('findBetween')->willReturn([$booking, $competingOpenRequest]);
        $manager->expects(self::once())->method('save')
            ->with(self::callback(static fn (SaunaBooking $saved): bool => $saved->status === SaunaBookingStatus::Accepted))
            ->willReturnArgument(0);

        $response = (new AcceptSaunaBookingUseCase($manager, $this->clock()))->execute('b1');

        self::assertSame(SaunaBookingStatus::Accepted, $response->status);
    }

    public function testRefusesWhenAnotherAcceptedBookingOverlaps(): void
    {
        $booking = $this->booking('b1', SaunaBookingStatus::Open);
        $manager = $this->createMock(SaunaBookingManagerInterface::class);
        $manager->method('get')->willReturn($booking);
        $manager->method('findBetween')->willReturn([$booking, $this->booking('b2', SaunaBookingStatus::Accepted)]);
        $manager->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        (new AcceptSaunaBookingUseCase($manager, $this->clock()))->execute('b1');
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-26T09:00:00'));

        return $clock;
    }

    private function booking(string $id, SaunaBookingStatus $status): SaunaBooking
    {
        $submittedAt = new \DateTimeImmutable('2026-09-25T10:00:00');

        return new SaunaBooking(
            id: $id,
            date: new \DateTimeImmutable('2026-10-05'),
            startTime: '18:00',
            endTime: '19:00',
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
