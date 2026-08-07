<?php

namespace App\Logic\Event\HelpRequest\Dto;

readonly class ParticipationIntervalInput
{
    public function __construct(
        public string $fromTime,
        public string $toTime,
    ) {
    }
}
