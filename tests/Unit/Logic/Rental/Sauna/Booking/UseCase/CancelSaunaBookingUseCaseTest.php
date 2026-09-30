<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;
use App\Logic\Rental\Sauna\Booking\SaunaGuestDirectoryInterface;
use App\Logic\Rental\Sauna\Booking\Service\SaunaRequesterEmailResolver;
use App\Logic\Rental\Sauna\Booking\UseCase\CancelSaunaBookingUseCase;
use PHPUnit\Framework\TestCase;

final class CancelSaunaBookingUseCaseTest extends TestCase
{
    public function testCancelsAndSendsTheConfirmationByDefault(): void
    {
        $notifier = $this->createMock(SaunaBookingNotifierInterface::class);
        $notifier->expects(self::once())->method('bookingCancelled')->with(
            self::callback(static fn (SaunaBooking $booking): bool => $booking->status === SaunaBookingStatus::Cancelled),
            'erika@example.test',
        );

        $response = $this->useCase($this->booking('erika@example.test'), $notifier)->execute('b1', true);

        self::assertSame(SaunaBookingStatus::Cancelled, $response->status);
    }

    public function testSendsNothingWhenTheConfirmationIsDeselected(): void
    {
        $notifier = $this->createMock(SaunaBookingNotifierInterface::class);
        $notifier->expects(self::never())->method('bookingCancelled');

        $response = $this->useCase($this->booking('erika@example.test'), $notifier)->execute('b1', false);

        self::assertSame(SaunaBookingStatus::Cancelled, $response->status);
    }

    public function testSendsNothingWithoutAnyEmailAddress(): void
    {
        $notifier = $this->createMock(SaunaBookingNotifierInterface::class);
        $notifier->expects(self::never())->method('bookingCancelled');

        $this->useCase($this->booking(null), $notifier)->execute('b1', true);
    }

    private function useCase(SaunaBooking $booking, SaunaBookingNotifierInterface $notifier): CancelSaunaBookingUseCase
    {
        $manager = $this->createStub(SaunaBookingManagerInterface::class);
        $manager->method('get')->willReturn($booking);
        $manager->method('save')->willReturnArgument(0);
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-30T12:00:00'));

        return new CancelSaunaBookingUseCase(
            $manager,
            new SaunaRequesterEmailResolver($this->createStub(SaunaGuestDirectoryInterface::class)),
            $notifier,
            $clock,
        );
    }

    private function booking(?string $email): SaunaBooking
    {
        $submittedAt = new \DateTimeImmutable('2026-09-25T10:00:00');

        return new SaunaBooking(
            id: 'b1',
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
            status: SaunaBookingStatus::Accepted,
            memberId: null,
            memberNumber: null,
            submittedAt: $submittedAt,
            updatedAt: $submittedAt,
        );
    }
}
