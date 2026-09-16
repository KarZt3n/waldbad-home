<?php

namespace App\Tests\Unit\Logic\Event\Template\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Event\Schedule\Model\EventScheduleKind;
use App\Logic\Event\Template\Model\EventTemplate;
use App\Logic\Event\Template\Model\EventTemplateActivity;
use App\Logic\Event\Template\Model\EventTemplateCallToAction;
use PHPUnit\Framework\TestCase;

final class EventTemplateTest extends TestCase
{
    public function testValidWorkAssignmentTemplateIsAccepted(): void
    {
        $now = new \DateTimeImmutable('2026-09-16T10:00:00+00:00');
        $template = new EventTemplate(
            id: 'template-1',
            kind: EventScheduleKind::WorkAssignment,
            title: 'Frühjahrsputz',
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
            activities: [
                new EventTemplateActivity(id: 'a1', position: 0, activityId: 'activity-1', requiredHelpers: 5, meetPlace: 'Haupteingang'),
            ],
            callToActions: [
                new EventTemplateCallToAction(id: 'cta-1', position: 0, label: 'Mehr erfahren', url: '/verein', pageId: null),
            ],
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertSame(EventScheduleKind::WorkAssignment, $template->kind);
        self::assertCount(1, $template->activities);
        self::assertSame('Haupteingang', $template->activities[0]->meetPlace);
    }

    public function testTitleIsRequired(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('Überschrift');

        $now = new \DateTimeImmutable();
        new EventTemplate(
            id: 'template-2', kind: EventScheduleKind::Event, title: '   ', content: '',
            mediaUrl: null, mediaAlt: null, mediaSource: null, layout: null, imageWidthPercent: null,
            verticalAlignment: null, textAlignment: null, imageFit: null, helpEnabled: false, helpButtonLabel: null,
            activities: [], callToActions: [], createdAt: $now, updatedAt: $now,
        );
    }

    public function testDuplicateActivityAssignmentIsRejected(): void
    {
        $this->expectException(BusinessRuleViolationException::class);
        $this->expectExceptionMessage('nur einmal zugeordnet');

        $now = new \DateTimeImmutable();
        new EventTemplate(
            id: 'template-3', kind: EventScheduleKind::WorkAssignment, title: 'Arbeitseinsatz', content: '',
            mediaUrl: null, mediaAlt: null, mediaSource: null, layout: null, imageWidthPercent: null,
            verticalAlignment: null, textAlignment: null, imageFit: null, helpEnabled: true, helpButtonLabel: null,
            activities: [
                new EventTemplateActivity(id: 'a1', position: 0, activityId: 'activity-1', requiredHelpers: 2),
                new EventTemplateActivity(id: 'a2', position: 1, activityId: 'activity-1', requiredHelpers: 3),
            ],
            callToActions: [], createdAt: $now, updatedAt: $now,
        );
    }

    public function testReviseKeepsIdentityAndKind(): void
    {
        $now = new \DateTimeImmutable('2026-09-16T10:00:00+00:00');
        $template = new EventTemplate(
            id: 'template-4', kind: EventScheduleKind::Event, title: 'Sommerfest', content: '',
            mediaUrl: null, mediaAlt: null, mediaSource: null, layout: null, imageWidthPercent: null,
            verticalAlignment: null, textAlignment: null, imageFit: null, helpEnabled: false, helpButtonLabel: null,
            activities: [], callToActions: [], createdAt: $now, updatedAt: $now,
        );

        $later = $now->modify('+1 day');
        $revised = $template->revise(
            title: 'Sommerfest (überarbeitet)', content: '<p>Neuer Text</p>',
            mediaUrl: null, mediaAlt: null, mediaSource: null, layout: null, imageWidthPercent: null,
            verticalAlignment: null, textAlignment: null, imageFit: null, helpEnabled: false, helpButtonLabel: null,
            activities: [], callToActions: [], updatedAt: $later,
        );

        self::assertSame('template-4', $revised->id);
        self::assertSame(EventScheduleKind::Event, $revised->kind);
        self::assertSame('Sommerfest (überarbeitet)', $revised->title);
        self::assertSame($now, $revised->createdAt);
        self::assertSame($later, $revised->updatedAt);
    }
}
