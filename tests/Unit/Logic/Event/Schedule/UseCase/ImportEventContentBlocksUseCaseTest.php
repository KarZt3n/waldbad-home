<?php

namespace App\Tests\Unit\Logic\Event\Schedule\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\EventActivityAssignment;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;
use App\Logic\Event\Schedule\Manager\EventScheduleManagerInterface;
use App\Logic\Event\Schedule\Model\EventSchedule;
use App\Logic\Event\Schedule\Model\EventScheduleKind;
use App\Logic\Event\Schedule\UseCase\ImportEventContentBlocksUseCase;
use PHPUnit\Framework\TestCase;

final class ImportEventContentBlocksUseCaseTest extends TestCase
{
    public function testImportsEventBlocksAndSkipsAlreadyImportedOnes(): void
    {
        $now = new \DateTimeImmutable('2026-08-28T12:00:00+02:00');
        $newBlock = new ContentBlock(
            type: ContentBlockType::Event,
            content: '<p>Details</p>',
            eventTitle: '19. Eisbaden',
            eventDate: '2026-01-03',
            eventTime: '14:00',
            eventIdentifier: 'event-1',
            eventHelpEnabled: true,
            eventActivities: [new EventActivityAssignment(activityId: 'activity-1', requiredHelpers: 5)],
        );
        $alreadyImportedBlock = new ContentBlock(
            type: ContentBlockType::Event,
            content: '',
            eventTitle: 'Bereits importiert',
            eventDate: '2026-02-01',
            eventTime: '10:00',
            eventIdentifier: 'event-2',
        );
        $page = $this->page([$newBlock, $alreadyImportedBlock], visible: true, status: PageStatus::Published);

        $pageManager = $this->createStub(PageManagerInterface::class);
        $pageManager->method('all')->willReturn([$page]);

        $existingSchedule = new EventSchedule(
            id: 'event-2', kind: EventScheduleKind::Event, title: 'Bereits importiert', date: '2026-02-01', time: '10:00',
            content: '', mediaUrl: null, mediaAlt: null, mediaSource: null, layout: null, imageWidthPercent: null,
            verticalAlignment: null, textAlignment: null, imageFit: null, helpEnabled: false, helpButtonLabel: null,
            visible: true, activities: [], callToActions: [], createdAt: $now, updatedAt: $now,
        );
        $scheduleManager = $this->createMock(EventScheduleManagerInterface::class);
        $scheduleManager->method('all')->willReturn([$existingSchedule]);
        $savedSchedule = null;
        $scheduleManager->expects(self::once())->method('save')
            ->willReturnCallback(function (EventSchedule $schedule) use (&$savedSchedule): EventSchedule {
                $savedSchedule = $schedule;

                return $schedule;
            });

        $identifierGenerator = $this->createStub(IdentifierGeneratorInterface::class);
        $identifierGenerator->method('generate')->willReturn('generated-id');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $result = (new ImportEventContentBlocksUseCase($pageManager, $scheduleManager, $identifierGenerator, $clock))->execute();

        self::assertSame(['19. Eisbaden'], $result->imported);
        self::assertSame(['Bereits importiert'], $result->skipped);
        self::assertInstanceOf(EventSchedule::class, $savedSchedule);
        self::assertSame('event-1', $savedSchedule->id);
        self::assertSame(EventScheduleKind::Event, $savedSchedule->kind);
        self::assertTrue($savedSchedule->visible);
        self::assertCount(1, $savedSchedule->activities);
        self::assertSame('activity-1', $savedSchedule->activities[0]->activityId);
        self::assertSame(5, $savedSchedule->activities[0]->requiredHelpers);
    }

    public function testDraftOrHiddenPagesProduceInvisibleSchedules(): void
    {
        $now = new \DateTimeImmutable('2026-08-28T12:00:00+02:00');
        $block = new ContentBlock(
            type: ContentBlockType::Event,
            content: '',
            eventTitle: 'Entwurf',
            eventDate: '2026-05-01',
            eventTime: '10:00',
            eventIdentifier: 'event-draft',
        );
        $page = $this->page([$block], visible: true, status: PageStatus::Draft);

        $pageManager = $this->createStub(PageManagerInterface::class);
        $pageManager->method('all')->willReturn([$page]);
        $scheduleManager = $this->createStub(EventScheduleManagerInterface::class);
        $scheduleManager->method('all')->willReturn([]);
        $savedSchedule = null;
        $scheduleManager->method('save')->willReturnCallback(function (EventSchedule $schedule) use (&$savedSchedule): EventSchedule {
            $savedSchedule = $schedule;

            return $schedule;
        });
        $identifierGenerator = $this->createStub(IdentifierGeneratorInterface::class);
        $identifierGenerator->method('generate')->willReturn('generated-id');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        (new ImportEventContentBlocksUseCase($pageManager, $scheduleManager, $identifierGenerator, $clock))->execute();

        self::assertInstanceOf(EventSchedule::class, $savedSchedule);
        self::assertFalse($savedSchedule->visible);
    }

    /** @param list<ContentBlock> $blocks */
    private function page(array $blocks, bool $visible, PageStatus $status): Page
    {
        $now = new \DateTimeImmutable('2026-08-01T00:00:00+02:00');

        return new Page(
            id: 'page-1',
            title: 'Veranstaltungen 2026',
            slug: 'veranstaltungen/2026',
            navigationLabel: '2026',
            parentId: null,
            blocks: $blocks,
            status: $status,
            visible: $visible,
            showInNavigation: true,
            navigationPosition: 0,
            seoTitle: null,
            seoDescription: null,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
            publishedAt: $status === PageStatus::Published ? $now : null,
        );
    }
}
