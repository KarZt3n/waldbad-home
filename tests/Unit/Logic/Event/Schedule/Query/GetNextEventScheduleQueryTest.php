<?php

namespace App\Tests\Unit\Logic\Event\Schedule\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Event\Schedule\Manager\EventScheduleManagerInterface;
use App\Logic\Event\Schedule\Model\EventSchedule;
use App\Logic\Event\Schedule\Model\EventScheduleKind;
use App\Logic\Event\Schedule\Query\GetNextEventScheduleQuery;
use PHPUnit\Framework\TestCase;

final class GetNextEventScheduleQueryTest extends TestCase
{
    public function testReturnsTheSoonestUpcomingVisibleScheduleOfTheRequestedKind(): void
    {
        $manager = $this->createStub(EventScheduleManagerInterface::class);
        $manager->method('all')->willReturn([
            $this->schedule('past', EventScheduleKind::Event, '2026-01-01', visible: true),
            $this->schedule('hidden-soonest', EventScheduleKind::Event, '2026-09-01', visible: false),
            $this->schedule('other-kind', EventScheduleKind::WorkAssignment, '2026-09-01', visible: true),
            $this->schedule('later', EventScheduleKind::Event, '2026-12-01', visible: true),
            $this->schedule('soonest-visible', EventScheduleKind::Event, '2026-09-05', visible: true),
        ]);
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-28T00:00:00+02:00'));

        $result = (new GetNextEventScheduleQuery($manager, $clock))->execute(EventScheduleKind::Event);

        self::assertNotNull($result);
        self::assertSame('soonest-visible', $result->id);
    }

    public function testNullKindIgnoresTheKindAndReturnsTheSoonestUpcomingScheduleOfEitherKind(): void
    {
        $manager = $this->createStub(EventScheduleManagerInterface::class);
        $manager->method('all')->willReturn([
            $this->schedule('event', EventScheduleKind::Event, '2026-09-05', visible: true),
            $this->schedule('work-assignment', EventScheduleKind::WorkAssignment, '2026-09-01', visible: true),
        ]);
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-28T00:00:00+02:00'));

        $result = (new GetNextEventScheduleQuery($manager, $clock))->execute(null);

        self::assertNotNull($result);
        self::assertSame('work-assignment', $result->id);
    }

    public function testReturnsNullWhenNothingIsUpcoming(): void
    {
        $manager = $this->createStub(EventScheduleManagerInterface::class);
        $manager->method('all')->willReturn([
            $this->schedule('past', EventScheduleKind::Event, '2026-01-01', visible: true),
        ]);
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-28T00:00:00+02:00'));

        self::assertNull((new GetNextEventScheduleQuery($manager, $clock))->execute(EventScheduleKind::Event));
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
