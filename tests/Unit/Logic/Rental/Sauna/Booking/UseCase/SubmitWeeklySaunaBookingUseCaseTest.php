<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaParticipantInput;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaSkippedDate;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitSaunaBookingRequest;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitWeeklySaunaBookingRequest;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Mapping\SaunaBookingModelFactory;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;
use App\Logic\Rental\Sauna\Booking\SaunaGuestMatcherInterface;
use App\Logic\Rental\Sauna\Booking\Service\SaunaSlotAvailability;
use App\Logic\Rental\Sauna\Booking\UseCase\SubmitWeeklySaunaBookingUseCase;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaClosure;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\Model\Weekday;
use App\Logic\Rental\Sauna\Terms\Manager\SaunaTermsManagerInterface;
use App\Logic\Rental\Sauna\Terms\Model\SaunaTerms;
use PHPUnit\Framework\TestCase;

/**
 * Saison ab 01.10.2026 montags 18–22 Uhr, am 19.10.2026 geschlossen; „jetzt“ ist der 25.09.2026.
 */
final class SubmitWeeklySaunaBookingUseCaseTest extends TestCase
{
    public function testCreatesOneBookingPerWeekWithSharedRequestIdUpToTheEndDate(): void
    {
        $saved = [];
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([]);
        $bookings->expects(self::once())->method('saveAll')->willReturnCallback(self::capture($saved));

        // Der 18.10. ist ein Sonntag: Die Serie endet mit dem letzten Montag davor.
        $response = $this->useCase($bookings)->execute($this->request('2026-10-18'));

        self::assertSame(['2026-10-05', '2026-10-12'], array_map(self::day(...), $saved));
        self::assertSame(['id-1'], array_values(array_unique(array_map(static fn (SaunaBooking $booking): ?string => $booking->requestId, $saved))));
        self::assertSame(['18:00–20:00'], array_values(array_unique(array_map(static fn (SaunaBooking $booking): string => $booking->startTime.'–'.$booking->endTime, $saved))));
        self::assertSame([3], array_values(array_unique(array_map(static fn (SaunaBooking $booking): int => count($booking->participants), $saved))));
        self::assertSame(['2026-10-05', '2026-10-12'], array_map(static fn (\DateTimeImmutable $date): string => $date->format('Y-m-d'), $response->bookedDates));
        self::assertSame([], $response->skippedDates);
    }

    public function testSkipsClosedAndBookedWeeksAndReportsThem(): void
    {
        $taken = $this->existingBooking('2026-10-26');
        $saved = [];
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturnCallback(
            static fn (\DateTimeImmutable $from): array => $from->format('Y-m-d') === '2026-10-26' ? [$taken] : [],
        );
        $bookings->expects(self::once())->method('saveAll')->willReturnCallback(self::capture($saved));

        $response = $this->useCase($bookings)->execute($this->request('2026-11-02'));

        self::assertSame(['2026-10-05', '2026-10-12', '2026-11-02'], array_map(self::day(...), $saved));
        self::assertSame(
            [['2026-10-19', 'Die Sauna ist am 19.10.2026 geschlossen (Revision).'], ['2026-10-26', 'Der gewählte Zeitraum ist bereits belegt.']],
            array_map(static fn (SaunaSkippedDate $skipped): array => [$skipped->date->format('Y-m-d'), $skipped->reason], $response->skippedDates),
        );
    }

    public function testRejectsTheWholeSeriesWhenTheFirstDateIsNotFree(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([$this->existingBooking('2026-10-05')]);
        $bookings->expects(self::never())->method('saveAll');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Der gewählte Zeitraum ist bereits belegt.');
        $this->useCase($bookings)->execute($this->request('2026-10-26'));
    }

    public function testRequiresAtLeastOneRepetition(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->expects(self::never())->method('saveAll');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Eine wöchentliche Wiederholung muss mindestens bis eine Woche nach dem ersten Termin reichen.');
        $this->useCase($bookings)->execute($this->request('2026-10-11'));
    }

    public function testLimitsTheSeriesToOneYear(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->expects(self::never())->method('saveAll');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Eine wöchentliche Wiederholung ist für höchstens 52 Wochen möglich.');
        $this->useCase($bookings)->execute($this->request('2027-10-05'));
    }

    public function testNotifiesOnceAboutAllRequestedDates(): void
    {
        $bookings = $this->createStub(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([]);
        $bookings->method('saveAll')->willReturnArgument(0);
        $notifier = $this->createMock(SaunaBookingNotifierInterface::class);
        $notifier->expects(self::never())->method('bookingSubmitted');
        $notifier->expects(self::once())->method('weeklyBookingsSubmitted')
            ->with(self::callback(static fn (array $saved): bool => count($saved) === 2));

        $this->useCase($bookings, $notifier)->execute($this->request('2026-10-18'));
    }

    /**
     * Fängt die an `saveAll()` übergebenen Buchungen in `$saved` auf und gibt sie unverändert zurück.
     *
     * @param list<SaunaBooking> $saved
     *
     * @return \Closure(array<mixed>): list<SaunaBooking>
     */
    private static function capture(array &$saved): \Closure
    {
        return static function (array $toSave) use (&$saved): array {
            $saved = array_values(array_filter($toSave, static fn (mixed $booking): bool => $booking instanceof SaunaBooking));

            return $saved;
        };
    }

    private static function day(SaunaBooking $booking): string
    {
        return $booking->date->format('Y-m-d');
    }

    private function useCase(SaunaBookingManagerInterface $bookings, ?SaunaBookingNotifierInterface $notifier = null): SubmitWeeklySaunaBookingUseCase
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
            closures: [new SaunaClosure(new \DateTimeImmutable('2026-10-19'), new \DateTimeImmutable('2026-10-19'), 'Revision')],
        );
        $seasons = $this->createStub(SaunaSeasonManagerInterface::class);
        $seasons->method('findCovering')->willReturn($season);
        $terms = $this->createStub(SaunaTermsManagerInterface::class);
        $terms->method('current')->willReturn(SaunaTerms::defaults());
        $matcher = $this->createStub(SaunaGuestMatcherInterface::class);
        $matcher->method('match')->willReturn(null);
        $identifiers = $this->createStub(IdentifierGeneratorInterface::class);
        $identifiers->method('generate')->willReturn('id-1');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        return new SubmitWeeklySaunaBookingUseCase(
            new SaunaBookingModelFactory($matcher, $identifiers, $clock),
            new SaunaSlotAvailability($seasons, $bookings),
            $terms,
            $bookings,
            $notifier ?? $this->createStub(SaunaBookingNotifierInterface::class),
        );
    }

    /** Erster Termin Montag, 05.10.2026, 18–20 Uhr für drei Personen. */
    private function request(string $repeatUntil): SubmitWeeklySaunaBookingRequest
    {
        return new SubmitWeeklySaunaBookingRequest(
            new SubmitSaunaBookingRequest(
                date: new \DateTimeImmutable('2026-10-05'),
                startTime: '18:00',
                endTime: '20:00',
                personCount: 3,
                firstName: 'Erika',
                lastName: 'Musterfrau',
                birthDate: null,
                email: null,
                message: '',
                participants: [
                    new SaunaParticipantInput('Erika', 'Musterfrau'),
                    new SaunaParticipantInput('Max', 'Muster'),
                    new SaunaParticipantInput('Gast', 'Eins'),
                ],
            ),
            new \DateTimeImmutable($repeatUntil),
        );
    }

    private function existingBooking(string $date): SaunaBooking
    {
        return new SaunaBooking(
            id: 'taken-'.$date,
            date: new \DateTimeImmutable($date),
            startTime: '18:00',
            endTime: '20:00',
            personCount: 2,
            priceCents: 2000,
            firstName: 'Max',
            lastName: 'Muster',
            birthDate: null,
            email: null,
            message: '',
            status: SaunaBookingStatus::Accepted,
            memberId: null,
            memberNumber: null,
            submittedAt: new \DateTimeImmutable('2026-09-01'),
            updatedAt: new \DateTimeImmutable('2026-09-01'),
        );
    }
}
