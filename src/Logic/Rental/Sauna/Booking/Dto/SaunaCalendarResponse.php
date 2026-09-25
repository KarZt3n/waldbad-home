<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

use App\Logic\Rental\Sauna\Terms\Dto\SaunaTermsResponse;

readonly class SaunaCalendarResponse
{
    /**
     * @param list<SaunaCalendarDay> $days
     */
    public function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        public array $days,
        /** Konditionen, die das Anfrageformular zur Personenauswahl und Preisanzeige benötigt. */
        public SaunaTermsResponse $terms,
        /** Beginn der laufenden oder nächsten kommenden Saison; `null` = aktuell keine Saison (alle abgelaufen/abgeschlossen). */
        public ?\DateTimeImmutable $seasonStartsOn,
        public ?\DateTimeImmutable $seasonEndsOn,
    ) {
    }
}
