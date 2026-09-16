<?php

namespace App\UI\Event\Handlers\HelpRequest;

use App\Logic\Event\HelpRequest\Event\EventHelpRequestBroadcastRequestedEvent;
use App\Logic\Event\HelpRequest\UseCase\SendEventHelpRequestBroadcastUseCase;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class SendEventHelpRequestBroadcastHandler
{
    public function __construct(private SendEventHelpRequestBroadcastUseCase $useCase)
    {
    }

    public function __invoke(EventHelpRequestBroadcastRequestedEvent $event): void
    {
        $result = $this->useCase->execute($event->subject, $event->body, [$event->recipient]);
        if ($result->failedCount > 0) {
            throw new \RuntimeException('Helfer-Rundmail konnte nicht gesendet werden.');
        }
    }
}
