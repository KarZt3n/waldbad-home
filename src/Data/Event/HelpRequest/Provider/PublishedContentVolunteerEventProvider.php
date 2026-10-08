<?php

namespace App\Data\Event\HelpRequest\Provider;

use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\EventActivityAssignment;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Event\HelpRequest\Model\VolunteerEvent;
use App\Logic\Event\HelpRequest\Model\VolunteerEventActivity;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;

readonly class PublishedContentVolunteerEventProvider implements VolunteerEventProviderInterface
{
    public function __construct(private PageManagerInterface $pageManager)
    {
    }

    public function findPublished(string $eventIdentifier): ?VolunteerEvent
    {
        return $this->findInPages($this->pageManager->publishedPages(), $eventIdentifier, true);
    }

    public function findCurrent(string $eventIdentifier): ?VolunteerEvent
    {
        return $this->findInPages($this->pageManager->all(), $eventIdentifier, false);
    }

    /** @param list<Page> $pages */
    private function findInPages(array $pages, string $eventIdentifier, bool $requiresEnabledHelp): ?VolunteerEvent
    {
        foreach ($pages as $page) {
            if ($requiresEnabledHelp && !$page->visible) {
                continue;
            }
            foreach ($page->blocks as $block) {
                if ($block->type !== ContentBlockType::Event
                    || ($requiresEnabledHelp && !$block->eventHelpEnabled)
                    || $block->eventIdentifier !== $eventIdentifier) {
                    continue;
                }
                if ($block->eventTitle === null || $block->eventDate === null || $block->eventTime === null) {
                    return null;
                }

                // Aktivitäten aus Legacy-Seitenblöcken waren stets als Checkbox auswählbar.
                return new VolunteerEvent($eventIdentifier, $block->eventTitle, $block->eventDate, $block->eventTime, array_map(
                    static fn (EventActivityAssignment $assignment): VolunteerEventActivity => new VolunteerEventActivity(
                        activityId: $assignment->activityId,
                        requiredHelpers: $assignment->requiredHelpers,
                        selectable: true,
                        time: $assignment->time,
                        meetTime: $assignment->meetTime,
                        meetPlace: $assignment->meetPlace,
                        remark: $assignment->remark,
                    ),
                    $block->eventActivities,
                ));
            }
        }

        return null;
    }
}
