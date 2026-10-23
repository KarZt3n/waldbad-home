<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

/** Ein Termin einer wöchentlichen Serie, der nicht angefragt werden konnte, mit Grund. */
readonly class SaunaSkippedDate
{
    public function __construct(
        public \DateTimeImmutable $date,
        public string $reason,
    ) {
    }
}
