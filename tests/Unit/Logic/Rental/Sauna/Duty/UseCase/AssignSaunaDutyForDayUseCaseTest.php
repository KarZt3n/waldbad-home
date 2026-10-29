<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Duty\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyResponse;
use App\Logic\Rental\Sauna\Duty\Manager\SaunaDutyManagerInterface;
use App\Logic\Rental\Sauna\Duty\Manager\SaunaDutyWaiverManagerInterface;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDuty;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyCandidate;
use App\Logic\Rental\Sauna\Duty\SaunaDutyMemberDirectoryInterface;
use App\Logic\Rental\Sauna\Duty\Service\SaunaDutyRoster;
use App\Logic\Rental\Sauna\Duty\UseCase\AssignSaunaDutyForDayUseCase;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\Model\Weekday;
use PHPUnit\Framework\TestCase;

final class AssignSaunaDutyForDayUseCaseTest extends TestCase
{
    private const string SATURDAY = '2026-10-10';

    public function testAssignsTheMemberToEveryDutyTimeOfTheDay(): void
    {
        $duties = $this->createMock(SaunaDutyManagerInterface::class);
        $duties->method('findBetween')->willReturn([]);
        $duties->expects(self::exactly(4))->method('save')->willReturnCallback(static fn (SaunaDuty $duty): SaunaDuty => $duty);

        $responses = $this->useCase($duties)->execute(new \DateTimeImmutable(self::SATURDAY), 'member-1');

        self::assertSame(['15:00', '17:00', '19:00', '21:00'], array_map(static fn (SaunaDutyResponse $duty): string => $duty->time, $responses));
        self::assertSame('1001', $responses[0]->memberNumber);
    }

    public function testSkipsTimesTheMemberAlreadyCovers(): void
    {
        $duties = $this->createMock(SaunaDutyManagerInterface::class);
        $duties->method('findBetween')->willReturn([
            new SaunaDuty('duty-1', new \DateTimeImmutable(self::SATURDAY), '17:00', 'member-1', '1001', 'Vera', 'Vorstand'),
            new SaunaDuty('duty-2', new \DateTimeImmutable(self::SATURDAY), '19:00', 'member-2', '1002', 'Otto', 'Anders'),
        ]);
        $duties->expects(self::exactly(3))->method('save')->willReturnCallback(static fn (SaunaDuty $duty): SaunaDuty => $duty);

        $responses = $this->useCase($duties)->execute(new \DateTimeImmutable(self::SATURDAY), 'member-1');

        self::assertSame(['15:00', '19:00', '21:00'], array_map(static fn (SaunaDutyResponse $duty): string => $duty->time, $responses));
    }

    public function testRejectsADayThatIsAlreadyFullyCovered(): void
    {
        $duties = $this->createMock(SaunaDutyManagerInterface::class);
        $duties->method('findBetween')->willReturn(array_map(
            static fn (string $time): SaunaDuty => new SaunaDuty('duty-'.$time, new \DateTimeImmutable(self::SATURDAY), $time, 'member-1', '1001', 'Vera', 'Vorstand'),
            ['15:00', '17:00', '19:00', '21:00'],
        ));
        $duties->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('bereits zu allen Zeiten eingetragen');
        $this->useCase($duties)->execute(new \DateTimeImmutable(self::SATURDAY), 'member-1');
    }

    public function testRejectsDaysWithoutSauna(): void
    {
        $duties = $this->createMock(SaunaDutyManagerInterface::class);
        $duties->method('findBetween')->willReturn([]);
        $duties->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('kein Saunadienst vorgesehen');
        $this->useCase($duties)->execute(new \DateTimeImmutable('2026-10-12'), 'member-1');
    }

    private function useCase(SaunaDutyManagerInterface $duties): AssignSaunaDutyForDayUseCase
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
        $directory = $this->createStub(SaunaDutyMemberDirectoryInterface::class);
        $directory->method('find')->willReturn(new SaunaDutyCandidate('member-1', '1001', 'Vera', 'Vorstand', 'Waldweg 1', null, true));
        $identifiers = $this->createStub(IdentifierGeneratorInterface::class);
        $identifiers->method('generate')->willReturn('duty-new');

        return new AssignSaunaDutyForDayUseCase(new SaunaDutyRoster($seasons, $bookings, $duties, $this->createStub(SaunaDutyWaiverManagerInterface::class)), $duties, $directory, $identifiers);
    }
}
