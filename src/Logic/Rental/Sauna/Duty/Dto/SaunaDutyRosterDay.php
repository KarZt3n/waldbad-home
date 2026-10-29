<?php

namespace App\Logic\Rental\Sauna\Duty\Dto;

readonly class SaunaDutyRosterDay
{
    /**
     * @param list<SaunaDutyPoint> $points   chronologisch; leer an Tagen ohne Saunazeiten
     * @param string|null          $waiverId Vermerk „kein Dienst nötig“ für den ganzen Tag
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public array $points,
        public bool $closed = false,
        public string $closedReason = '',
        public ?string $waiverId = null,
    ) {
    }
}
