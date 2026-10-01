<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaParticipantInput;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitSaunaBookingRequest;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Mapping\SaunaBookingModelFactory;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaGuestMatch;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;
use App\Logic\Rental\Sauna\Booking\SaunaGuestMatcherInterface;
use App\Logic\Rental\Sauna\Booking\Service\SaunaSlotAvailability;
use App\Logic\Rental\Sauna\Booking\UseCase\SubmitSaunaBookingUseCase;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\Model\Weekday;
use App\Logic\Rental\Sauna\Terms\Manager\SaunaTermsManagerInterface;
use App\Logic\Rental\Sauna\Terms\Model\SaunaTerms;
use PHPUnit\Framework\TestCase;

final class SubmitSaunaBookingUseCaseTest extends TestCase
{
    public function testStoresGroupSizeProratedPriceAndMemberMatch(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([]);
        $bookings->expects(self::once())->method('save')
            ->with(self::callback(static fn (SaunaBooking $booking): bool => $booking->personCount === 4
                && $booking->priceCents === 3000
                && $booking->memberNumber === 'M-7'))
            ->willReturnArgument(0);

        $response = $this->useCase($bookings)->execute($this->request(personCount: 4, endTime: '21:00'));

        self::assertSame(3000, $response->priceCents);
    }

    public function testNotifiesAboutTheSavedBooking(): void
    {
        $bookings = $this->createStub(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([]);
        $bookings->method('save')->willReturnArgument(0);
        $notifier = $this->createMock(SaunaBookingNotifierInterface::class);
        $notifier->expects(self::once())->method('bookingSubmitted')
            ->with(self::callback(static fn (SaunaBooking $booking): bool => $booking->id === 'booking-1' && $booking->personCount === 4));

        $this->useCase($bookings, $notifier)->execute($this->request(personCount: 4, endTime: '21:00'));
    }

    public function testRejectedRequestSendsNoNotification(): void
    {
        $bookings = $this->createStub(SaunaBookingManagerInterface::class);
        $notifier = $this->createMock(SaunaBookingNotifierInterface::class);
        $notifier->expects(self::never())->method('bookingSubmitted');

        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($bookings, $notifier)->execute($this->request(personCount: 1, endTime: '19:00'));
    }

    public function testRejectsSinglePerson(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($bookings)->execute($this->request(personCount: 1, endTime: '19:00'));
    }

    public function testRejectsGroupAboveMaximum(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($bookings)->execute($this->request(personCount: 7, endTime: '19:00'));
    }

    public function testRegularRequestOutsideSlotGridIsRejected(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([]);
        $bookings->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($bookings)->execute($this->request(personCount: 3, endTime: '15:00', date: '2026-10-06', startTime: '13:15'));
    }

    private function useCase(SaunaBookingManagerInterface $bookings, ?SaunaBookingNotifierInterface $notifier = null): SubmitSaunaBookingUseCase
    {
        $now = new \DateTimeImmutable('2026-09-25T10:00:00');
        $season = new SaunaSeason(
            id: 'season-1',
            startsOn: new \DateTimeImmutable('2026-10-01'),
            endsOn: null,
            slotDurationMinutes: 60,
            openingHours: [new SaunaOpeningHours(Weekday::Monday, '18:00', '22:00')],
            createdAt: $now,
            updatedAt: $now,
        );
        $seasons = $this->createStub(SaunaSeasonManagerInterface::class);
        $seasons->method('findCovering')->willReturnCallback(
            static fn (\DateTimeImmutable $date): ?SaunaSeason => $season->slotsOn($date) === [] ? null : $season,
        );
        $terms = $this->createStub(SaunaTermsManagerInterface::class);
        $terms->method('current')->willReturn(SaunaTerms::defaults());
        $matcher = $this->createStub(SaunaGuestMatcherInterface::class);
        $matcher->method('match')->willReturn(new SaunaGuestMatch('member-7', 'M-7'));
        $identifiers = $this->createStub(IdentifierGeneratorInterface::class);
        $identifiers->method('generate')->willReturn('booking-1');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        return new SubmitSaunaBookingUseCase(
            new SaunaBookingModelFactory($matcher, $identifiers, $clock),
            new SaunaSlotAvailability($seasons, $bookings),
            $terms,
            $bookings,
            $notifier ?? $this->createStub(SaunaBookingNotifierInterface::class),
        );
    }

    public function testRequiresTheNamesOfAllPersons(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Für jede Person der Gruppe sind Vorname und Nachname anzugeben.');
        $this->useCase($bookings)->execute($this->request(3, '20:00', participantCount: 2));
    }

    /** Ohne `$participantCount` mit genau so vielen Namen wie Personen (Erika Musterfrau zuerst). */
    private function request(
        int $personCount,
        string $endTime,
        string $date = '2026-10-05',
        string $startTime = '18:00',
        ?int $participantCount = null,
    ): SubmitSaunaBookingRequest {
        return new SubmitSaunaBookingRequest(
            date: new \DateTimeImmutable($date),
            startTime: $startTime,
            endTime: $endTime,
            personCount: $personCount,
            firstName: 'Erika',
            lastName: 'Musterfrau',
            birthDate: new \DateTimeImmutable('1990-01-01'),
            email: null,
            message: '',
            participants: array_map(
                static fn (int $index): SaunaParticipantInput => $index === 0
                    ? new SaunaParticipantInput('Erika', 'Musterfrau')
                    : new SaunaParticipantInput('Gast', (string) $index),
                range(0, max(0, ($participantCount ?? $personCount) - 1)),
            ),
        );
    }
}
