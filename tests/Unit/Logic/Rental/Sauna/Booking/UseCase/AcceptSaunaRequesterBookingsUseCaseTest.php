<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;
use App\Logic\Rental\Sauna\Booking\SaunaGuestDirectoryInterface;
use App\Logic\Rental\Sauna\Booking\Service\SaunaBookingAcceptance;
use App\Logic\Rental\Sauna\Booking\Service\SaunaRequesterEmailResolver;
use App\Logic\Rental\Sauna\Booking\UseCase\AcceptSaunaRequesterBookingsUseCase;
use PHPUnit\Framework\TestCase;

final class AcceptSaunaRequesterBookingsUseCaseTest extends TestCase
{
    /**
     * Alle offenen Anmeldungen der Person — auch aus zwei verschiedenen Anfragen — werden angenommen,
     * ein bereits abgelehnter Tag und die Anmeldungen anderer Personen bleiben unberührt; die Person
     * erhält eine einzige Bestätigung an die zuletzt angegebene E-Mail-Adresse.
     */
    public function testAcceptsAllOpenBookingsOfThePersonAcrossRequestsAndConfirmsThemInOneMail(): void
    {
        $bookings = [
            $this->booking('d1', '2026-10-15', SaunaBookingStatus::Open, 'request-1', null, '2026-09-29T10:00:00'),
            $this->booking('d2', '2026-10-16', SaunaBookingStatus::Rejected, 'request-1', null, '2026-09-29T10:00:00'),
            $this->booking('d3', '2026-10-18', SaunaBookingStatus::Open, 'request-2', 'erika@example.test', '2026-09-30T10:00:00'),
            $this->booking('other', '2026-10-19', SaunaBookingStatus::Open, 'request-3', null, '2026-09-30T11:00:00', lastName: 'Andere'),
        ];
        $manager = $this->createMock(SaunaBookingManagerInterface::class);
        $manager->method('all')->willReturn($bookings);
        $manager->method('findBetween')->willReturn([]);
        $manager->expects(self::once())->method('saveAll')->willReturnArgument(0);
        $notifier = $this->createMock(SaunaBookingNotifierInterface::class);
        $notifier->expects(self::once())->method('bookingsAccepted')->with(
            self::callback(static fn (array $accepted): bool => array_column($accepted, 'id') === ['d1', 'd3']),
            'erika@example.test',
        );

        $responses = $this->useCase($manager, $notifier)->execute($bookings[0]->requesterKey());

        self::assertSame(['d1', 'd3'], array_column($responses, 'id'));
        self::assertSame([SaunaBookingStatus::Accepted, SaunaBookingStatus::Accepted], array_column($responses, 'status'));
    }

    public function testAcceptsNothingWhenOneDayIsAlreadyTaken(): void
    {
        $bookings = [
            $this->booking('d1', '2026-10-15', SaunaBookingStatus::Open, 'request-1'),
            $this->booking('d3', '2026-10-18', SaunaBookingStatus::Open, 'request-2'),
        ];
        $manager = $this->createMock(SaunaBookingManagerInterface::class);
        $manager->method('all')->willReturn($bookings);
        $manager->method('findBetween')->willReturnCallback(
            fn (\DateTimeImmutable $from): array => $from->format('Y-m-d') === '2026-10-18'
                ? [$this->booking('taken', '2026-10-18', SaunaBookingStatus::Accepted, null, lastName: 'Andere')]
                : [],
        );
        $manager->expects(self::never())->method('saveAll');
        $notifier = $this->createMock(SaunaBookingNotifierInterface::class);
        $notifier->expects(self::never())->method('bookingsAccepted');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('18.10.2026, 17:00–19:00 Uhr');
        $this->useCase($manager, $notifier)->execute($bookings[0]->requesterKey());
    }

    public function testRefusesWithoutOpenBookings(): void
    {
        $booking = $this->booking('d1', '2026-10-15', SaunaBookingStatus::Accepted, 'request-1');
        $manager = $this->createStub(SaunaBookingManagerInterface::class);
        $manager->method('all')->willReturn([$booking]);

        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($manager)->execute($booking->requesterKey());
    }

    private function useCase(SaunaBookingManagerInterface $manager, ?SaunaBookingNotifierInterface $notifier = null): AcceptSaunaRequesterBookingsUseCase
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-30T12:00:00'));

        return new AcceptSaunaRequesterBookingsUseCase($manager, new SaunaBookingAcceptance(
            $manager,
            new SaunaRequesterEmailResolver($this->createStub(SaunaGuestDirectoryInterface::class)),
            $notifier ?? $this->createStub(SaunaBookingNotifierInterface::class),
            $clock,
        ));
    }

    private function booking(
        string $id,
        string $date,
        SaunaBookingStatus $status,
        ?string $requestId,
        ?string $email = null,
        string $submittedAt = '2026-09-30T10:00:00',
        string $lastName = 'Musterfrau',
    ): SaunaBooking {
        return new SaunaBooking(
            id: $id,
            date: new \DateTimeImmutable($date),
            startTime: '17:00',
            endTime: '19:00',
            personCount: 2,
            priceCents: 2000,
            firstName: 'Erika',
            lastName: $lastName,
            birthDate: new \DateTimeImmutable('1990-01-01'),
            email: $email,
            message: '',
            status: $status,
            memberId: null,
            memberNumber: null,
            submittedAt: new \DateTimeImmutable($submittedAt),
            updatedAt: new \DateTimeImmutable($submittedAt),
            individual: $requestId !== null,
            requestId: $requestId,
        );
    }
}
