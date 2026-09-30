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
        /** Letzter buchbarer Tag dieser Saison (Ende bzw. Tag vor dem Abschluss); `null` = bis auf Weiteres. */
        public ?\DateTimeImmutable $seasonEndsOn,
    ) {
    }
}
