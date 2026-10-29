<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Exception\InvalidSaunaBookingApprovalTokenException;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingApprovalTokenManagerInterface;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingApprovalToken;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;
use App\Logic\Rental\Sauna\Booking\SaunaGuestDirectoryInterface;
use App\Logic\Rental\Sauna\Booking\Service\SaunaBookingAcceptance;
use App\Logic\Rental\Sauna\Booking\Service\SaunaRequesterEmailResolver;
use App\Logic\Rental\Sauna\Booking\UseCase\ApproveSaunaBookingsByTokenUseCase;
use PHPUnit\Framework\TestCase;

final class ApproveSaunaBookingsByTokenUseCaseTest extends TestCase
{
    private const string NOW = '2026-10-29T12:00:00';

    /** Ein inzwischen in der Redaktion abgelehnter Termin bleibt abgelehnt; der Link ist danach verbraucht. */
    public function testAcceptsTheRemainingOpenBookingsWithTheChosenDutyAndConsumesTheToken(): void
    {
        $bookings = ['d1' => $this->booking('d1', SaunaBookingStatus::Open), 'd2' => $this->booking('d2', SaunaBookingStatus::Rejected)];
        $manager = $this->createMock(SaunaBookingManagerInterface::class);
        $manager->method('get')->willReturnCallback(static fn (string $id): SaunaBooking => $bookings[$id]);
        $manager->method('findBetween')->willReturn([]);
        $manager->expects(self::once())->method('saveAll')->willReturnArgument(0);
        $tokens = $this->createMock(SaunaBookingApprovalTokenManagerInterface::class);
        $tokens->expects(self::once())->method('findByHash')->with(hash('sha256', 'raw'))->willReturn($this->token());
        $tokens->expects(self::once())->method('save')->with(self::callback(
            static fn (SaunaBookingApprovalToken $token): bool => $token->usedAt?->format(DATE_ATOM) === (new \DateTimeImmutable(self::NOW))->format(DATE_ATOM),
        ));

        $accepted = $this->useCase($tokens, $manager)->execute('raw', false);

        self::assertSame(['d1'], array_column($accepted, 'id'));
        self::assertFalse($accepted[0]->dutyRequired);
    }

    public function testRejectsExpiredAndUsedTokens(): void
    {
        foreach ([$this->token(expiresAt: self::NOW), $this->token(usedAt: '2026-10-28T09:00:00')] as $token) {
            $tokens = $this->createStub(SaunaBookingApprovalTokenManagerInterface::class);
            $tokens->method('findByHash')->willReturn($token);
            $manager = $this->createMock(SaunaBookingManagerInterface::class);
            $manager->expects(self::never())->method('saveAll');

            try {
                $this->useCase($tokens, $manager)->execute('raw', true);
                self::fail('Der Link darf nicht mehr gelten.');
            } catch (InvalidSaunaBookingApprovalTokenException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRefusesWhenNothingIsOpenAnymore(): void
    {
        $tokens = $this->createMock(SaunaBookingApprovalTokenManagerInterface::class);
        $tokens->method('findByHash')->willReturn($this->token());
        $tokens->expects(self::never())->method('save');
        $manager = $this->createStub(SaunaBookingManagerInterface::class);
        $manager->method('get')->willReturn($this->booking('d1', SaunaBookingStatus::Accepted));

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('bereits in der Redaktion bearbeitet');
        $this->useCase($tokens, $manager)->execute('raw', true);
    }

    private function useCase(SaunaBookingApprovalTokenManagerInterface $tokens, SaunaBookingManagerInterface $manager): ApproveSaunaBookingsByTokenUseCase
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable(self::NOW));

        return new ApproveSaunaBookingsByTokenUseCase($tokens, $manager, new SaunaBookingAcceptance(
            $manager,
            new SaunaRequesterEmailResolver($this->createStub(SaunaGuestDirectoryInterface::class)),
            $this->createStub(SaunaBookingNotifierInterface::class),
            $clock,
        ), $clock);
    }

    private function token(string $expiresAt = '2026-11-12T12:00:00', ?string $usedAt = null): SaunaBookingApprovalToken
    {
        return new SaunaBookingApprovalToken(
            'token-1',
            hash('sha256', 'raw'),
            ['d1', 'd2'],
            new \DateTimeImmutable($expiresAt),
            $usedAt === null ? null : new \DateTimeImmutable($usedAt),
        );
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
