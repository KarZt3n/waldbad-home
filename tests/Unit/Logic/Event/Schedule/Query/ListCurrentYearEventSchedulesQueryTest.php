<?php

namespace App\Tests\Unit\Logic\Event\Schedule\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Event\Schedule\Dto\EventScheduleResponse;
use App\Logic\Event\Schedule\Manager\EventScheduleManagerInterface;
use App\Logic\Event\Schedule\Model\EventSchedule;
use App\Logic\Event\Schedule\Model\EventScheduleKind;
use App\Logic\Event\Schedule\Model\EventSchedulePeriod;
use App\Logic\Event\Schedule\Query\ListCurrentYearEventSchedulesQuery;
use PHPUnit\Framework\TestCase;

final class ListCurrentYearEventSchedulesQueryTest extends TestCase
{
    public function testCurrentYearListsOnlyVisibleSchedulesOfTheKindInTheRunningYearSortedByDate(): void
    {
        $result = $this->query()->execute(EventScheduleKind::Event);

        self::assertSame(['this-year-past', 'this-year-later'], $this->ids($result));
    }

    public function testFromCurrentYearAlsoListsLaterYearsButNoEarlierOnes(): void
    {
        $result = $this->query()->execute(EventScheduleKind::Event, EventSchedulePeriod::FromCurrentYear);

        self::assertSame(['this-year-past', 'this-year-later', 'next-year', 'in-two-years'], $this->ids($result));
    }

    private function query(): ListCurrentYearEventSchedulesQuery
    {
        $manager = $this->createStub(EventScheduleManagerInterface::class);
        $manager->method('all')->willReturn([
            $this->schedule('in-two-years', EventScheduleKind::Event, '2028-05-01', visible: true),
            $this->schedule('last-year', EventScheduleKind::Event, '2025-12-31', visible: true),
            $this->schedule('this-year-later', EventScheduleKind::Event, '2026-12-12', visible: true),
            $this->schedule('next-year', EventScheduleKind::Event, '2027-01-01', visible: true),
            $this->schedule('hidden-next-year', EventScheduleKind::Event, '2027-03-01', visible: false),
            $this->schedule('work-next-year', EventScheduleKind::WorkAssignment, '2027-04-01', visible: true),
            $this->schedule('this-year-past', EventScheduleKind::Event, '2026-01-10', visible: true),
        ]);
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-28T00:00:00+02:00'));

        return new ListCurrentYearEventSchedulesQuery($manager, $clock);
    }

    /**
     * @param list<EventScheduleResponse> $schedules
     *
     * @return list<string>
     */
    private function ids(array $schedules): array
    {
        return array_map(static fn (EventScheduleResponse $schedule): string => $schedule->id, $schedules);
    }

    private function schedule(string $id, EventScheduleKind $kind, string $date, bool $visible): EventSchedule
    {
        $now = new \DateTimeImmutable('2026-08-01T00:00:00+02:00');

        return new EventSchedule(
            id: $id, kind: $kind, title: 'Titel', date: $date, time: '10:00', content: '', mediaUrl: null,
            mediaAlt: null, mediaSource: null, layout: null, imageWidthPercent: null, verticalAlignment: null,
            textAlignment: null, imageFit: null, helpEnabled: false, helpButtonLabel: null, visible: $visible,
            activities: [], callToActions: [], createdAt: $now, updatedAt: $now,
        );
    }
}
