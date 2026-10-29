<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Rental\Sauna\Booking\Exception\InvalidSaunaBookingApprovalTokenException;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingApprovalTokenManagerInterface;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingApprovalToken;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\UseCase\RejectSaunaBookingsByTokenUseCase;
use PHPUnit\Framework\TestCase;

final class RejectSaunaBookingsByTokenUseCaseTest extends TestCase
{
    private const string NOW = '2026-10-29T12:00:00';

    public function testRejectsTheOpenBookingsAndConsumesTheToken(): void
    {
        $bookings = ['d1' => $this->booking('d1', SaunaBookingStatus::Open), 'd2' => $this->booking('d2', SaunaBookingStatus::Accepted)];
        $manager = $this->createMock(SaunaBookingManagerInterface::class);
        $manager->method('get')->willReturnCallback(static fn (string $id): SaunaBooking => $bookings[$id]);
        $manager->expects(self::once())->method('saveAll')->willReturnArgument(0);
        $tokens = $this->createMock(SaunaBookingApprovalTokenManagerInterface::class);
        $tokens->expects(self::once())->method('findByHash')->with(hash('sha256', 'raw'))->willReturn($this->token(null));
        $tokens->expects(self::once())->method('save')->with(self::callback(static fn (SaunaBookingApprovalToken $token): bool => $token->usedAt !== null));

        $rejected = $this->useCase($tokens, $manager)->execute('raw');

        self::assertSame(['d1'], array_column($rejected, 'id'));
        self::assertSame(SaunaBookingStatus::Rejected, $rejected[0]->status);
    }

    public function testRejectsAUsedToken(): void
    {
        $tokens = $this->createStub(SaunaBookingApprovalTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($this->token('2026-10-29T08:00:00'));
        $manager = $this->createMock(SaunaBookingManagerInterface::class);
        $manager->expects(self::never())->method('saveAll');

        $this->expectException(InvalidSaunaBookingApprovalTokenException::class);
        $this->useCase($tokens, $manager)->execute('raw');
    }

    private function useCase(SaunaBookingApprovalTokenManagerInterface $tokens, SaunaBookingManagerInterface $manager): RejectSaunaBookingsByTokenUseCase
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable(self::NOW));

        return new RejectSaunaBookingsByTokenUseCase($tokens, $manager, $clock);
    }

    private function token(?string $usedAt): SaunaBookingApprovalToken
    {
        return new SaunaBookingApprovalToken('token-1', hash('sha256', 'raw'), ['d1', 'd2'], new \DateTimeImmutable('2026-11-12T12:00:00'), $usedAt === null ? null : new \DateTimeImmutable($usedAt));
    }

    private function booking(string $id, SaunaBookingStatus $status): SaunaBooking
    {
        $submittedAt = new \DateTimeImmutable('2026-10-28T10:00:00');

        return new SaunaBooking(
            id: $id,
            date: new \DateTimeImmutable('2026-11-05'),
            startTime: '17:00',
            endTime: '19:00',
            personCount: 2,
            priceCents: 2000,
            firstName: 'Erika',
            lastName: 'Musterfrau',
            birthDate: null,
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
