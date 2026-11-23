<?php

namespace App\Logic\Event\HelpRequest\Dto;

readonly class ParticipationIntervalInput
{
    public function __construct(
        public string $fromTime,
        /** `null`, solange die Person noch anwesend ist. */
        public ?string $toTime,
    ) {
    }
}
