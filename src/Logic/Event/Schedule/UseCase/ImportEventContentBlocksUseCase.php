<?php

namespace App\Logic\Event\Schedule\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\EventActivityAssignment;
use App\Logic\Content\Page\Model\EventCallToAction;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;
use App\Logic\Event\Schedule\Dto\ImportEventContentBlocksResult;
use App\Logic\Event\Schedule\Manager\EventScheduleManagerInterface;
use App\Logic\Event\Schedule\Model\EventSchedule;
use App\Logic\Event\Schedule\Model\EventScheduleActivity;
use App\Logic\Event\Schedule\Model\EventScheduleCallToAction;
use App\Logic\Event\Schedule\Model\EventScheduleKind;

/**
 * Übernimmt bestehende, direkt in Seiten gepflegte „Veranstaltung“-Inhaltsblöcke
 * (ContentBlockType::Event) in das eigenständige Modul „Veranstaltungen“. Die ursprünglichen
 * Inhaltsblöcke bleiben dabei unverändert erhalten (u. a. weil andere Seiten sie per
 * „Veranstaltung einbetten“ referenzieren können) – es wird lediglich zusätzlich ein
 * gleichwertiger Datensatz im neuen Modul angelegt.
 *
 * Die stabile `eventIdentifier` des Blocks wird 1:1 als Kennung der neuen Veranstaltung
 * übernommen, damit bereits eingegangene Helferanmeldungen weiterhin derselben Veranstaltung
 * zugeordnet bleiben. Bereits importierte Veranstaltungen (gleiche Kennung existiert schon im
 * Modul) werden übersprungen, der Befehl kann also gefahrlos mehrfach ausgeführt werden.
 */
readonly class ImportEventContentBlocksUseCase
{
    public function __construct(
        private PageManagerInterface $pageManager,
        private EventScheduleManagerInterface $scheduleManager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(): ImportEventContentBlocksResult
    {
        $existingIds = array_map(static fn (EventSchedule $schedule): string => $schedule->id, $this->scheduleManager->all());

        $imported = [];
        $skipped = [];
        foreach ($this->pageManager->all() as $page) {
            foreach ($page->blocks as $block) {
                if ($block->type !== ContentBlockType::Event) {
                    continue;
                }

                $id = $block->eventIdentifier ?? $this->identifierGenerator->generate();
                $label = $block->eventTitle ?? $id;
                if (in_array($id, $existingIds, true)) {
                    $skipped[] = $label;
                    continue;
                }

                $this->scheduleManager->save($this->toSchedule($id, $block, $page));
                $existingIds[] = $id;
                $imported[] = $label;
            }
        }

        return new ImportEventContentBlocksResult($imported, $skipped);
    }

    private function toSchedule(string $id, ContentBlock $block, Page $page): EventSchedule
    {
        $now = $this->clock->now();

        return new EventSchedule(
            id: $id,
            kind: EventScheduleKind::Event,
            title: $block->eventTitle ?? '',
            date: $block->eventDate ?? '',
            time: $block->eventTime ?? '',
            content: $block->content,
            mediaUrl: $block->mediaUrl,
            mediaAlt: $block->mediaAlt,
            mediaSource: $block->mediaSource,
            layout: $block->layout,
            imageWidthPercent: $block->imageWidthPercent,
            verticalAlignment: $block->verticalAlignment,
            textAlignment: $block->textAlignment,
            imageFit: $block->imageFit,
            helpEnabled: $block->eventHelpEnabled,
            helpButtonLabel: $block->eventHelpButtonLabel,
            visible: $page->visible && $page->status === PageStatus::Published,
            activities: $this->activities($block->eventActivities),
            callToActions: $this->callToActions($block->eventCallToActions),
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * @param list<EventActivityAssignment> $assignments
     * @return list<EventScheduleActivity>
     */
    private function activities(array $assignments): array
    {
        $activities = [];
        foreach ($assignments as $position => $assignment) {
            $activities[] = new EventScheduleActivity(
                id: $this->identifierGenerator->generate(),
                position: $position,
                activityId: $assignment->activityId,
                requiredHelpers: $assignment->requiredHelpers,
                time: $assignment->time,
                meetTime: $assignment->meetTime,
                meetPlace: $assignment->meetPlace,
                remark: $assignment->remark,
            );
        }

        return $activities;
    }

    /**
     * @param list<EventCallToAction> $callToActions
     * @return list<EventScheduleCallToAction>
     */
    private function callToActions(array $callToActions): array
    {
        $mapped = [];
        foreach ($callToActions as $position => $action) {
            $mapped[] = new EventScheduleCallToAction(
                id: $this->identifierGenerator->generate(),
                position: $position,
                label: $action->label,
                url: $action->url,
                pageId: $action->pageId,
            );
        }

        return $mapped;
    }
}
