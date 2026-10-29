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
     * @param string|null                    $waiverId   eigener Vermerk „kein Dienst nötig“ für diesen Zeitpunkt
     * @param bool                           $dutyWaived kein Dienst nötig — durch eigenen Vermerk oder den des ganzen Tages
     */
    public function __construct(
        public string $time,
        public SaunaDutyPointState $state,
        public SaunaDutyPointKind $kind,
        public array $startingBookings,
        public array $endingBookings,
        public array $runningBookings,
        public array $duties,
        public ?string $waiverId = null,
        public bool $dutyWaived = false,
    ) {
    }
}
