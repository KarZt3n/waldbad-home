<?php

namespace App\Data\Infrastructure\Messenger;

use App\Logic\Common\Messaging\AsyncEventPublisherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class SymfonyAsyncEventPublisher implements AsyncEventPublisherInterface
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    public function publish(object $event): void
    {
        $this->messageBus->dispatch($event);
    }
}
