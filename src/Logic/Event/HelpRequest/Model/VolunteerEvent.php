<?php

namespace App\Logic\Event\HelpRequest\Model;

readonly class VolunteerEvent
{
    /** @param list<VolunteerEventActivity> $activities */
    public function __construct(
        public string $identifier,
        public string $title,
        public string $date,
        public string $time,
        public array $activities,
    ) {
    }
}
