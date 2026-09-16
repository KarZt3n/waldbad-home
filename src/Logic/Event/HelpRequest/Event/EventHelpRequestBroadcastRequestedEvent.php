<?php

namespace App\Logic\Event\HelpRequest\Event;

readonly class EventHelpRequestBroadcastRequestedEvent
{
    public function __construct(
        public string $subject,
        public string $body,
        public string $recipient,
    ) {
    }
}
