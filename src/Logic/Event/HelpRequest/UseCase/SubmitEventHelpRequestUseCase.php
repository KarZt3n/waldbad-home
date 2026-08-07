<?php

namespace App\Logic\Event\HelpRequest\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Content\Page\Manager\PageManagerInterface;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\Event\HelpRequest\Dto\EventHelpRequestResponse;
use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;

readonly class SubmitEventHelpRequestUseCase
{
    public function __construct(
        private EventHelpRequestManagerInterface $manager,
        private PageManagerInterface $pageManager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $eventIdentifier, string $firstName, string $lastName, string $message): EventHelpRequestResponse
    {
        $event = null;
        foreach ($this->pageManager->publishedPages() as $page) {
            if (!$page->visible) {
                continue;
            }
            foreach ($page->blocks as $block) {
                if ($block->type === ContentBlockType::Event && $block->eventHelpEnabled && $block->eventIdentifier === $eventIdentifier) {
                    $event = $block;
                    break 2;
                }
            }
        }
        if ($event === null || $event->eventTitle === null || $event->eventDate === null || $event->eventTime === null) {
            throw new BusinessRuleViolationException('Für diese Veranstaltung ist keine Helferanmeldung verfügbar.');
        }

        $now = $this->clock->now();
        $request = new EventHelpRequest(
            id: $this->identifierGenerator->generate(),
            eventIdentifier: $eventIdentifier,
            eventTitle: $event->eventTitle,
            eventDate: $event->eventDate,
            eventTime: $event->eventTime,
            firstName: trim($firstName),
            lastName: trim($lastName),
            message: trim($message),
            status: EventHelpRequestStatus::New,
            participationMinutes: null,
            participationIntervals: [],
            submittedAt: $now,
            updatedAt: $now,
        );

        return EventHelpRequestResponse::fromRequest($this->manager->save($request));
    }
}
