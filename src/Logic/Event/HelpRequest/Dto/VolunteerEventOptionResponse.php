<?php

namespace App\Logic\Event\HelpRequest\Dto;

use App\Logic\Event\HelpRequest\Model\VolunteerEvent;

readonly class VolunteerEventOptionResponse
{
    public function __construct(
        public string $eventIdentifier,
        public string $eventTitle,
        public string $eventDate,
        public string $eventTime,
    ) {
    }

    public static function fromEvent(VolunteerEvent $event): self
    {
        return new self($event->identifier, $event->title, $event->date, $event->time);
    }
}
