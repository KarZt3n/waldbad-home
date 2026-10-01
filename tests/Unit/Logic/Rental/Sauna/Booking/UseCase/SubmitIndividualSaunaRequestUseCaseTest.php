<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaParticipantInput;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaRequestDayInput;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitIndividualSaunaRequest;
use App\Logic\Rental\Sauna\Booking\Exception\SaunaRequestDayConflictException;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Mapping\SaunaBookingModelFactory;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingParticipant;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\Model\SaunaGuestMatch;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;
use App\Logic\Rental\Sauna\Booking\SaunaGuestMatcherInterface;
use App\Logic\Rental\Sauna\Booking\Service\SaunaSlotAvailability;
use App\Logic\Rental\Sauna\Booking\UseCase\SubmitIndividualSaunaRequestUseCase;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\Model\Weekday;
use App\Logic\Rental\Sauna\Terms\Manager\SaunaTermsManagerInterface;
use App\Logic\Rental\Sauna\Terms\Model\SaunaTerms;
use PHPUnit\Framework\TestCase;

final class SubmitIndividualSaunaRequestUseCaseTest extends TestCase
{
    /**
     * Je Wunschtag eine eigene Buchung mit gemeinsamer Anfrage-Kennung, eigener Personenzahl,
     * eigenen Namen und eigenem Preis — innerhalb der Saison, aber außerhalb ihres Zeitrasters. Eine einzige
     * Benachrichtigung nennt alle Tage.
     */
    public function testCreatesOneBookingPerDayAndNotifiesOnce(): void
    {
        $saved = [];
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([]);
        $bookings->expects(self::once())->method('saveAll')->willReturnCallback(static function (array $toSave) use (&$saved): array {
            $saved = array_values(array_filter($toSave, static fn (mixed $booking): bool => $booking instanceof SaunaBooking));

            return $saved;
        });
        $notifier = $this->createMock(SaunaBookingNotifierInterface::class);
        $notifier->expects(self::once())->method('individualRequestSubmitted')->with(self::countOf(2));

        $response = $this->useCase($bookings, $notifier)->execute($this->request([
            $this->day('2026-10-08', '13:15', '15:30', ['Max Muster', 'Mia Muster']),
            $this->day('2026-10-11', '10:00', '12:00', ['Max Muster', 'Mia Muster', 'Tom Muster']),
        ]));

        self::assertSame(['id-2', 'id-6'], $response->bookingIds);
        self::assertSame('id-1', $response->requestId);
        self::assertSame(
            [
                ['2026-10-08', '13:15', 3, 2250, 'id-1', ['Erika Musterfrau', 'Max Muster', 'Mia Muster']],
                ['2026-10-11', '10:00', 4, 2000, 'id-1', ['Erika Musterfrau', 'Max Muster', 'Mia Muster', 'Tom Muster']],
            ],
            array_map(static fn (SaunaBooking $booking): array => [
                $booking->date->format('Y-m-d'),
                $booking->startTime,
                $booking->personCount,
                $booking->priceCents,
                $booking->requestId,
                array_map(static fn (SaunaBookingParticipant $participant): string => $participant->firstName.' '.$participant->lastName, $booking->participants),
            ], $saved),
        );
        self::assertSame(SaunaBookingStatus::Open, $saved[0]->status);
        self::assertTrue($saved[0]->individual);
    }

    public function testEveryDayMustBeFree(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturnCallback(
            fn (\DateTimeImmutable $from): array => $from->format('Y-m-d') === '2026-10-11' ? [$this->existing('2026-10-11', '11:00', '13:00')] : [],
        );
        $bookings->expects(self::never())->method('saveAll');

        $exception = $this->conflict($this->useCase($bookings), $this->request([
            $this->day('2026-10-08', '13:15', '15:30', ['Max Muster']),
            $this->day('2026-10-11', '10:00', '12:00', ['Max Muster']),
        ]));

        self::assertSame('Wunschtag 2: Der gewählte Zeitraum ist bereits belegt.', $exception->getMessage());
        self::assertSame(['days' => [1]], $exception->details());
    }

    public function testDaysMustNotOverlapEachOther(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([]);
        $bookings->expects(self::never())->method('saveAll');

        $exception = $this->conflict($this->useCase($bookings), $this->request([
            $this->day('2026-10-08', '13:00', '15:00', ['Max Muster']),
            $this->day('2026-10-09', '13:00', '15:00', ['Max Muster']),
            $this->day('2026-10-08', '14:00', '16:00', ['Max Muster']),
        ]));

        self::assertStringContainsString('Wunschtag 1: Die Wunschtage dürfen sich zeitlich nicht überschneiden.', $exception->getMessage());
        self::assertSame(['days' => [0, 2]], $exception->details());
    }

    private function conflict(SubmitIndividualSaunaRequestUseCase $useCase, SubmitIndividualSaunaRequest $request): SaunaRequestDayConflictException
    {
        try {
            $useCase->execute($request);
        } catch (SaunaRequestDayConflictException $exception) {
            return $exception;
        }
        self::fail('Erwartet wurde eine SaunaRequestDayConflictException.');
    }

    public function testNamesMustMatchThePersonCount(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->expects(self::never())->method('saveAll');

        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($bookings)->execute($this->request([
            new SaunaRequestDayInput(new \DateTimeImmutable('2026-10-08'), '13:00', '15:00', 3, [
                new SaunaParticipantInput('Erika', 'Musterfrau'),
                new SaunaParticipantInput('Max', 'Muster'),
            ]),
        ]));
    }

    public function testEachDayMustRespectTheGroupSize(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->expects(self::never())->method('saveAll');

        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($bookings)->execute($this->request([$this->day('2026-10-08', '13:00', '15:00', [])]));
    }

    public function testEveryDayMustLastAtLeastTheMinimumDuration(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([]);
        $bookings->expects(self::never())->method('saveAll');

        $this->expectException(SaunaRequestDayConflictException::class);
        $this->expectExceptionMessage('Wunschtag 2: Eine Sauna-Anfrage muss mindestens 2 Stunden dauern.');
        $this->useCase($bookings)->execute($this->request([
            $this->day('2026-10-08', '13:00', '15:00', ['Max Muster']),
            $this->day('2026-10-09', '13:00', '14:45', ['Max Muster']),
        ]));
    }

    public function testDaysOutsideTheSeasonAreRejected(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([]);
        $bookings->expects(self::never())->method('saveAll');

        $this->expectException(SaunaRequestDayConflictException::class);
        $this->expectExceptionMessage('Wunschtag 2: Der 05.01.2027 liegt außerhalb der Sauna-Saison.');
        $this->useCase($bookings)->execute($this->request([
            $this->day('2026-10-08', '13:00', '15:00', ['Max Muster']),
            $this->day('2027-01-05', '13:00', '15:00', ['Max Muster']),
        ]));
    }

    private function useCase(SaunaBookingManagerInterface $bookings, ?SaunaBookingNotifierInterface $notifier = null): SubmitIndividualSaunaRequestUseCase
    {
        $terms = $this->createStub(SaunaTermsManagerInterface::class);
        $terms->method('current')->willReturn(SaunaTerms::defaults());
        $matcher = $this->createStub(SaunaGuestMatcherInterface::class);
        $matcher->method('match')->willReturn(new SaunaGuestMatch('member-7', 'M-7'));
        $counter = 0;
        $identifiers = $this->createStub(IdentifierGeneratorInterface::class);
        $identifiers->method('generate')->willReturnCallback(static function () use (&$counter): string {
            return 'id-'.++$counter;
        });
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-09-30T10:00:00'));

        return new SubmitIndividualSaunaRequestUseCase(
            new SaunaBookingModelFactory($matcher, $identifiers, $clock),
            new SaunaSlotAvailability($this->seasons(), $bookings),
            $terms,
            $bookings,
            $notifier ?? $this->createStub(SaunaBookingNotifierInterface::class),
        );
    }

    /** Saison Oktober bis Dezember 2026; nur montags im Zeitraster, individuell aber an jedem Tag anfragbar. */
    private function seasons(): SaunaSeasonManagerInterface
    {
        $now = new \DateTimeImmutable('2026-09-01T00:00:00');
        $season = new SaunaSeason(
            id: 'season-1',
            startsOn: new \DateTimeImmutable('2026-10-01'),
            endsOn: new \DateTimeImmutable('2026-12-31'),
            slotDurationMinutes: 60,
            openingHours: [new SaunaOpeningHours(Weekday::Monday, '17:00', '21:00')],
            createdAt: $now,
            updatedAt: $now,
        );
        $seasons = $this->createStub(SaunaSeasonManagerInterface::class);
        $seasons->method('findCovering')->willReturnCallback(
            static fn (\DateTimeImmutable $date): ?SaunaSeason => $season->covers($date) ? $season : null,
        );

        return $seasons;
    }

    /**
     * @param non-empty-list<SaunaRequestDayInput> $days
     */
    private function request(array $days): SubmitIndividualSaunaRequest
    {
        return new SubmitIndividualSaunaRequest('Erika', 'Musterfrau', new \DateTimeImmutable('1990-01-01'), null, '', $days);
    }

    /**
     * Die anfragende Person (Erika Musterfrau) ist immer Person 1, `$others` sind die übrigen.
     *
     * @param list<string> $others
     */
    private function day(string $date, string $startTime, string $endTime, array $others): SaunaRequestDayInput
    {
        $participants = [new SaunaParticipantInput('Erika', 'Musterfrau')];
        foreach ($others as $name) {
            [$firstName, $lastName] = explode(' ', $name);
            $participants[] = new SaunaParticipantInput($firstName, $lastName);
        }

        return new SaunaRequestDayInput(new \DateTimeImmutable($date), $startTime, $endTime, count($participants), $participants);
    }

    private function existing(string $date, string $startTime, string $endTime): SaunaBooking
    {
        $submittedAt = new \DateTimeImmutable('2026-09-20T10:00:00');

        return new SaunaBooking('existing', new \DateTimeImmutable($date), $startTime, $endTime, 2, 2000, 'Max', 'Mustermann', new \DateTimeImmutable('1985-05-05'), null, '', SaunaBookingStatus::Open, null, null, $submittedAt, $submittedAt);
    }
}
