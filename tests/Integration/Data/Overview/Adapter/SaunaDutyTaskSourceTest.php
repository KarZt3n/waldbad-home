<?php

namespace App\Tests\Integration\Data\Overview\Adapter;

use App\Data\Overview\Adapter\SaunaDutyTaskSource;
use App\Logic\Common\ClockInterface;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyPoint;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyResponse;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyRosterDay;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyPointKind;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyPointState;
use App\Logic\Rental\Sauna\Duty\Query\GetSaunaDutyRosterQuery;
use PHPUnit\Framework\TestCase;

final class SaunaDutyTaskSourceTest extends TestCase
{
    public function testReportsUpcomingRequiredPointsWithoutDuty(): void
    {
        $today = new \DateTimeImmutable('2026-10-28 00:00');
        $tomorrow = new \DateTimeImmutable('2026-10-29 00:00');
        $roster = $this->createMock(GetSaunaDutyRosterQuery::class);
        $roster->expects(self::once())->method('execute')->with($today, 28)->willReturn([
            new SaunaDutyRosterDay($today, [
                $this->point('15:00', SaunaDutyPointState::Required),
                $this->point('19:00', SaunaDutyPointState::Required),
                $this->point('21:00', SaunaDutyPointState::Required, assigned: true),
                $this->point('22:00', SaunaDutyPointState::Required, waived: true),
                $this->point('23:00', SaunaDutyPointState::Requested),
            ]),
            new SaunaDutyRosterDay($tomorrow, [
                $this->point('17:00', SaunaDutyPointState::Free),
                $this->point('24:00', SaunaDutyPointState::Required),
            ]),
        ]);
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-28 16:30'));

        $dates = (new SaunaDutyTaskSource($roster, $clock))->openItemDates();

        self::assertSame(
            ['2026-10-28 19:00', '2026-10-30 00:00'],
            array_map(static fn (\DateTimeImmutable $date): string => $date->format('Y-m-d H:i'), $dates),
        );
    }

    private function point(string $time, SaunaDutyPointState $state, bool $assigned = false, bool $waived = false): SaunaDutyPoint
    {
        return new SaunaDutyPoint(
            time: $time,
            state: $state,
            kind: SaunaDutyPointKind::Entry,
            startingBookings: [],
            endingBookings: [],
            runningBookings: [],
            duties: $assigned ? [new SaunaDutyResponse('duty-1', new \DateTimeImmutable('2026-10-28'), $time, 'member-1', '1001', 'Max', 'Muster')] : [],
            dutyWaived: $waived,
        );
    }
}
