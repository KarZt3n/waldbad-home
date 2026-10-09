<?php

namespace App\Logic\Rental\Sauna\Duty\Dto;

readonly class SaunaDutyRosterDay
{
    /** @param list<SaunaDutyPoint> $points chronologisch; leer an Tagen ohne Saunazeiten */
    public function __construct(
        public \DateTimeImmutable $date,
        public array $points,
        public bool $closed = false,
        public string $closedReason = '',
    ) {
    }
}
