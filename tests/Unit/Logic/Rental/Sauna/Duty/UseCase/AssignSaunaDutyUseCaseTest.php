<?php

namespace App\Tests\Unit\Logic\Rental\Sauna\Duty\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Duty\Manager\SaunaDutyManagerInterface;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDuty;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyCandidate;
use App\Logic\Rental\Sauna\Duty\SaunaDutyMemberDirectoryInterface;
use App\Logic\Rental\Sauna\Duty\Service\SaunaDutyRoster;
use App\Logic\Rental\Sauna\Duty\UseCase\AssignSaunaDutyUseCase;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\Model\Weekday;
use PHPUnit\Framework\TestCase;

final class AssignSaunaDutyUseCaseTest extends TestCase
{
    private const string SATURDAY = '2026-10-10';

    public function testStoresMemberSnapshotForAnEntryTime(): void
    {
        $duties = $this->createMock(SaunaDutyManagerInterface::class);
        $duties->method('findBetween')->willReturn([]);
        $duties->expects(self::once())->method('save')->willReturnCallback(static fn (SaunaDuty $duty): SaunaDuty => $duty);

        $response = $this->useCase($duties)->execute(new \DateTimeImmutable(self::SATURDAY), '17:00', 'member-1');

        self::assertSame('17:00', $response->time);
        self::assertSame('1001', $response->memberNumber);
        self::assertSame('Vorstand', $response->lastName);
    }

    public function testRejectsTimesWithoutEntry(): void
    {
        $duties = $this->createStub(SaunaDutyManagerInterface::class);
        $duties->method('findBetween')->willReturn([]);

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('kein Saunadienst vorgesehen');
        $this->useCase($duties)->execute(new \DateTimeImmutable(self::SATURDAY), '16:00', 'member-1');
    }

    public function testRejectsTheSamePersonTwiceAtOneEntry(): void
    {
        $duties = $this->createMock(SaunaDutyManagerInterface::class);
        $duties->method('findBetween')->willReturn([
            new SaunaDuty('duty-1', new \DateTimeImmutable(self::SATURDAY), '17:00', 'member-1', '1001', 'Vera', 'Vorstand'),
        ]);
        $duties->expects(self::never())->method('save');

        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('bereits eingetragen');
        $this->useCase($duties)->execute(new \DateTimeImmutable(self::SATURDAY), '17:00', 'member-1');
    }

    private function useCase(SaunaDutyManagerInterface $duties): AssignSaunaDutyUseCase
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

        return new AssignSaunaDutyUseCase(new SaunaDutyRoster($seasons, $bookings, $duties), $duties, $directory, $identifiers);
    }
}
