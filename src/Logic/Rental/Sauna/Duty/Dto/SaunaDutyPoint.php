<?php

namespace App\Logic\Rental\Sauna\Duty\Dto;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyPointKind;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyPointState;

/** Dienstzeitpunkt eines Tages: Einlass, Übergang zwischen zwei Buchungen oder Abschließen. */
readonly class SaunaDutyPoint
{
    /**
     * @param list<SaunaDutyBookingResponse> $startingBookings Buchungen, die zu diesem Zeitpunkt beginnen
     * @param list<SaunaDutyBookingResponse> $endingBookings   Buchungen, die zu diesem Zeitpunkt enden
     * @param list<SaunaDutyBookingResponse> $runningBookings  früher begonnene Buchungen, die noch laufen
     * @param list<SaunaDutyResponse>        $duties
     */
    public function __construct(
        public string $time,
        public SaunaDutyPointState $state,
        public SaunaDutyPointKind $kind,
        public array $startingBookings,
        public array $endingBookings,
        public array $runningBookings,
        public array $duties,
    ) {
    }
}
