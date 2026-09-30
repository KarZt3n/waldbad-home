<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

/** Ein Wunschtag einer individuellen Anfrage — wird zu einer eigenen Buchung. */
readonly class SaunaRequestDayInput
{
    /**
     * @param list<SaunaParticipantInput> $participants genau `$personCount` Personen
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public string $startTime,
        public string $endTime,
        public int $personCount,
        public array $participants,
    ) {
    }
}
