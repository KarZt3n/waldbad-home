<?php

namespace App\Logic\Event\HelpRequest\Model;

use App\Logic\Content\Page\Model\EventActivityAssignment;

readonly class VolunteerEvent
{
    /** @param list<EventActivityAssignment> $activities */
    public function __construct(
        public string $identifier,
        public string $title,
        public string $date,
        public string $time,
        public array $activities,
    ) {
    }
}
