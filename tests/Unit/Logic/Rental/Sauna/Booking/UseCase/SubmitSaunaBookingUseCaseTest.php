<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitSaunaBookingRequest;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Mapping\SaunaBookingModelFactory;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\Model\SaunaGuestMatch;
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

    public function testIndividualRequestIsAllowedOutsideSeasonAndSlotGrid(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([]);
        $bookings->expects(self::once())->method('save')
            ->with(self::callback(static fn (SaunaBooking $booking): bool => $booking->individual
                && $booking->startTime === '13:15'
                && $booking->priceCents === 1750))
            ->willReturnArgument(0);

        // Dienstag, außerhalb des Wochenplans (nur montags) und nicht am Raster ausgerichtet.
        $this->useCase($bookings)->execute($this->request(personCount: 3, endTime: '15:00', date: '2026-10-06', startTime: '13:15', individual: true));
    }

    public function testIndividualRequestMustNotOverlapExistingRequest(): void
    {
        $existing = new SaunaBooking(
            id: 'existing',
            date: new \DateTimeImmutable('2026-10-06'),
            startTime: '14:00',
            endTime: '16:00',
            personCount: 2,
            priceCents: 2000,
            firstName: 'Max',
            lastName: 'Mustermann',
            birthDate: new \DateTimeImmutable('1985-05-05'),
            email: null,
            message: '',
            status: SaunaBookingStatus::Open,
            memberId: null,
            memberNumber: null,
            submittedAt: new \DateTimeImmutable('2026-09-20T10:00:00'),
            updatedAt: new \DateTimeImmutable('2026-09-20T10:00:00'),
        );
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([$existing]);
        $bookings->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('bereits belegt');
        $this->useCase($bookings)->execute($this->request(personCount: 3, endTime: '15:00', date: '2026-10-06', startTime: '13:15', individual: true));
    }

    public function testRegularRequestOutsideSlotGridIsRejected(): void
    {
        $bookings = $this->createMock(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([]);
        $bookings->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        $this->useCase($bookings)->execute($this->request(personCount: 3, endTime: '15:00', date: '2026-10-06', startTime: '13:15'));
    }

    private function useCase(SaunaBookingManagerInterface $bookings): SubmitSaunaBookingUseCase
    {
        $now = new \DateTimeImmutable('2026-09-25T10:00:00');
        $season = new SaunaSeason(
            id: 'season-1',
            name: 'Wintersaison',
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
        );
    }

    private function request(
        int $personCount,
        string $endTime,
        string $date = '2026-10-05',
        string $startTime = '18:00',
        bool $individual = false,
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
            individual: $individual,
        );
    }
}
