<?php

namespace App\Logic\Event\HelpRequest\Dto;

readonly class EventHelpRequestBroadcastResponse
{
    public function __construct(
        public int $recipientCount,
        public int $sentCount,
        public int $failedCount,
    ) {
    }
}
