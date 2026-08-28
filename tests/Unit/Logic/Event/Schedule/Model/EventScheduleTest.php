<?php

namespace App\Tests\Unit\Logic\Event\Schedule\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Event\Schedule\Model\EventSchedule;
use App\Logic\Event\Schedule\Model\EventScheduleActivity;
use App\Logic\Event\Schedule\Model\EventScheduleCallToAction;
use App\Logic\Event\Schedule\Model\EventScheduleKind;
use PHPUnit\Framework\TestCase;

final class EventScheduleTest extends TestCase
{
    public function testValidWorkAssignmentIsAccepted(): void
    {
        $now = new \DateTimeImmutable('2026-08-28T10:00:00+00:00');
        $schedule = new EventSchedule(
            id: 'schedule-1',
            kind: EventScheduleKind::WorkAssignment,
            title: 'Frühjahrsputz',
            date: '2026-04-18',
            time: '09:00',
            content: '<p>Wir machen das Waldbad startklar.</p>',
            mediaUrl: null,
            mediaAlt: null,
            mediaSource: null,
            layout: null,
            imageWidthPercent: null,
            verticalAlignment: null,
            textAlignment: null,
            imageFit: null,
            helpEnabled: true,
            helpButtonLabel: 'Ich möchte helfen!',
            visible: true,
            activities: [
                new EventScheduleActivity(
                    id: 'activity-assignment-1',
                    position: 0,
                    activityId: 'activity-1',
                    requiredHelpers: 5,
                    time: '09:30',
                    meetTime: '09:15',
                    meetPlace: 'Haupteingang',
                    remark: 'Bitte Handschuhe mitbringen.',
                ),
            ],
            callToActions: [
                new EventScheduleCallToAction(id: 'cta-1', position: 0, label: 'Mehr erfahren', url: '/verein', pageId: null),
            ],
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertSame(EventScheduleKind::WorkAssignment, $schedule->kind);
        self::assertCount(1, $schedule->activities);
        self::assertSame('09:15', $schedule->activities[0]->meetTime);
    }

    public function testTitleIsRequired(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Überschrift');

        $now = new \DateTimeImmutable();
        new EventSchedule(
            id: 'schedule-2', kind: EventScheduleKind::Event, title: '   ', date: '2026-04-18', time: '09:00',
            content: '', mediaUrl: null, mediaAlt: null, mediaSource: null, layout: null, imageWidthPercent: null,
            verticalAlignment: null, textAlignment: null, imageFit: null, helpEnabled: false, helpButtonLabel: null,
            visible: true, activities: [], callToActions: [], createdAt: $now, updatedAt: $now,
        );
    }

    public function testDateMustBeValid(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Datum');

        $now = new \DateTimeImmutable();
        new EventSchedule(
            id: 'schedule-3', kind: EventScheduleKind::Event, title: 'Sommerfest', date: '2026-13-40', time: '09:00',
            content: '', mediaUrl: null, mediaAlt: null, mediaSource: null, layout: null, imageWidthPercent: null,
            verticalAlignment: null, textAlignment: null, imageFit: null, helpEnabled: false, helpButtonLabel: null,
            visible: true, activities: [], callToActions: [], createdAt: $now, updatedAt: $now,
        );
    }

    public function testDuplicateActivityAssignmentIsRejected(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('nur einmal zugeordnet');

        $now = new \DateTimeImmutable();
        new EventSchedule(
            id: 'schedule-4', kind: EventScheduleKind::WorkAssignment, title: 'Arbeitseinsatz', date: '2026-04-18',
            time: '09:00', content: '', mediaUrl: null, mediaAlt: null, mediaSource: null, layout: null,
            imageWidthPercent: null, verticalAlignment: null, textAlignment: null, imageFit: null, helpEnabled: true,
            helpButtonLabel: null, visible: true,
            activities: [
                new EventScheduleActivity(id: 'a1', position: 0, activityId: 'activity-1', requiredHelpers: 2),
                new EventScheduleActivity(id: 'a2', position: 1, activityId: 'activity-1', requiredHelpers: 3),
            ],
            callToActions: [], createdAt: $now, updatedAt: $now,
        );
    }

    public function testActivityMeetTimeMustBeValidFormat(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Treffzeit');

        new EventScheduleActivity(id: 'a1', position: 0, activityId: 'activity-1', requiredHelpers: 2, meetTime: '9:5');
    }

    public function testReviseKeepsIdentityAndKind(): void
    {
        $now = new \DateTimeImmutable('2026-08-28T10:00:00+00:00');
        $schedule = new EventSchedule(
            id: 'schedule-5', kind: EventScheduleKind::Event, title: 'Sommerfest', date: '2026-06-01', time: '15:00',
            content: '', mediaUrl: null, mediaAlt: null, mediaSource: null, layout: null, imageWidthPercent: null,
            verticalAlignment: null, textAlignment: null, imageFit: null, helpEnabled: false, helpButtonLabel: null,
            visible: true, activities: [], callToActions: [], createdAt: $now, updatedAt: $now,
        );

        $later = $now->modify('+1 day');
        $revised = $schedule->revise(
            title: 'Sommerfest (verschoben)', date: '2026-06-08', time: '16:00', content: '<p>Neuer Termin</p>',
            mediaUrl: null, mediaAlt: null, mediaSource: null, layout: null, imageWidthPercent: null,
            verticalAlignment: null, textAlignment: null, imageFit: null, helpEnabled: false, helpButtonLabel: null,
            visible: true, activities: [], callToActions: [], updatedAt: $later,
        );

        self::assertSame('schedule-5', $revised->id);
        self::assertSame(EventScheduleKind::Event, $revised->kind);
        self::assertSame('2026-06-08', $revised->date);
        self::assertSame($now, $revised->createdAt);
        self::assertSame($later, $revised->updatedAt);
    }
}
