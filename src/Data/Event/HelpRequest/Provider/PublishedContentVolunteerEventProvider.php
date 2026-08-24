<?php

namespace App\Data\Event\HelpRequest\Provider;

use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Event\HelpRequest\Model\VolunteerEvent;
use App\Logic\Event\HelpRequest\VolunteerEventProviderInterface;

readonly class PublishedContentVolunteerEventProvider implements VolunteerEventProviderInterface
{
    public function __construct(private PageManagerInterface $pageManager)
    {
    }

    public function findPublished(string $eventIdentifier): ?VolunteerEvent
    {
        foreach ($this->pageManager->publishedPages() as $page) {
            if (!$page->visible) {
                continue;
            }
            foreach ($page->blocks as $block) {
                if ($block->type !== ContentBlockType::Event || !$block->eventHelpEnabled || $block->eventIdentifier !== $eventIdentifier) {
                    continue;
                }
                if ($block->eventTitle === null || $block->eventDate === null || $block->eventTime === null) {
                    return null;
                }

                return new VolunteerEvent($eventIdentifier, $block->eventTitle, $block->eventDate, $block->eventTime, $block->eventActivities);
            }
        }

        return null;
    }
}
