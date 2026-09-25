<?php

namespace App\Logic\Rental\Sauna\Season\Model;

readonly class SaunaTimeSlot
{
    public function __construct(
        public \DateTimeImmutable $date,
        public string $startTime,
        public string $endTime,
    ) {
    }

    public function startsAt(): \DateTimeImmutable
    {
        return $this->date->modify($this->startTime);
    }
}
