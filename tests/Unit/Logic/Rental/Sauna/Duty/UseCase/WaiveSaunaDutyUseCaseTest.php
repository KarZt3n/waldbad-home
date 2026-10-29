<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Duty\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Duty\Manager\SaunaDutyManagerInterface;
use App\Logic\Rental\Sauna\Duty\Manager\SaunaDutyWaiverManagerInterface;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyWaiver;
use App\Logic\Rental\Sauna\Duty\Service\SaunaDutyRoster;
use App\Logic\Rental\Sauna\Duty\UseCase\WaiveSaunaDutyUseCase;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\Model\Weekday;
use PHPUnit\Framework\TestCase;

final class WaiveSaunaDutyUseCaseTest extends TestCase
{
    private const string SATURDAY = '2026-10-10';

    public function testWaivesASingleDutyTime(): void
    {
        $waivers = $this->createMock(SaunaDutyWaiverManagerInterface::class);
        $waivers->method('findBetween')->willReturn([]);
        $waivers->expects(self::once())->method('save')->willReturnCallback(static fn (SaunaDutyWaiver $waiver): SaunaDutyWaiver => $waiver);

        $response = $this->useCase($waivers)->execute(new \DateTimeImmutable(self::SATURDAY), '17:00');

        self::assertSame('waiver-new', $response->id);
        self::assertSame('17:00', $response->time);
    }

    public function testWholeDayReplacesTheSingleWaiversOfThatDay(): void
    {
        $waivers = $this->createMock(SaunaDutyWaiverManagerInterface::class);
        $waivers->method('findBetween')->willReturn([new SaunaDutyWaiver('waiver-17', new \DateTimeImmutable(self::SATURDAY), '17:00')]);
        $waivers->expects(self::once())->method('delete')->with('waiver-17');
        $waivers->expects(self::once())->method('save')->willReturnCallback(static fn (SaunaDutyWaiver $waiver): SaunaDutyWaiver => $waiver);

        $response = $this->useCase($waivers)->execute(new \DateTimeImmutable(self::SATURDAY), null);

        self::assertNull($response->time);
    }

    public function testRejectsASecondWaiverForTheSameTime(): void
    {
        $waivers = $this->createMock(SaunaDutyWaiverManagerInterface::class);
        $waivers->method('findBetween')->willReturn([new SaunaDutyWaiver('waiver-17', new \DateTimeImmutable(self::SATURDAY), '17:00')]);
        $waivers->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('bereits kein Dienst nötig');
        $this->useCase($waivers)->execute(new \DateTimeImmutable(self::SATURDAY), '17:00');
    }

    public function testRejectsSingleTimesWhenTheWholeDayIsAlreadyWaived(): void
    {
        $waivers = $this->createMock(SaunaDutyWaiverManagerInterface::class);
        $waivers->method('findBetween')->willReturn([new SaunaDutyWaiver('day', new \DateTimeImmutable(self::SATURDAY), null)]);
        $waivers->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('für den ganzen Tag kein Dienst nötig');
        $this->useCase($waivers)->execute(new \DateTimeImmutable(self::SATURDAY), '17:00');
    }

    public function testRejectsDaysAndTimesWithoutSaunaDuty(): void
    {
        $waivers = $this->createMock(SaunaDutyWaiverManagerInterface::class);
        $waivers->method('findBetween')->willReturn([]);
        $waivers->expects(self::never())->method('save');

        try {
            $this->useCase($waivers)->execute(new \DateTimeImmutable(self::SATURDAY), '16:00');
            self::fail('16:00 liegt nicht im Raster.');
        } catch (BusinessRuleViolationException $exception) {
            self::assertStringContainsString('um 16:00 Uhr kein Saunadienst vorgesehen', $exception->getMessage());
        }

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('kein Saunadienst vorgesehen');
        $this->useCase($waivers)->execute(new \DateTimeImmutable('2026-10-11'), null);
    }

    private function useCase(SaunaDutyWaiverManagerInterface $waivers): WaiveSaunaDutyUseCase
    {
        $now = new \DateTimeImmutable('2026-09-01T00:00:00');
        $seasons = $this->createStub(SaunaSeasonManagerInterface::class);
        $seasons->method('all')->willReturn([new SaunaSeason(
            id: 'season-1',
            startsOn: new \DateTimeImmutable('2026-10-01'),
            endsOn: null,
            slotDurationMinutes: 120,
            openingHours: [new SaunaOpeningHours(Weekday::Saturday, '15:00', '21:00')],
            createdAt: $now,
            updatedAt: $now,
        )]);
        $bookings = $this->createStub(SaunaBookingManagerInterface::class);
        $bookings->method('findBetween')->willReturn([]);
        $duties = $this->createStub(SaunaDutyManagerInterface::class);
        $duties->method('findBetween')->willReturn([]);
        $identifiers = $this->createStub(IdentifierGeneratorInterface::class);
        $identifiers->method('generate')->willReturn('waiver-new');

        return new WaiveSaunaDutyUseCase(new SaunaDutyRoster($seasons, $bookings, $duties, $waivers), $waivers, $identifiers);
    }
}
