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
        /** Laufende oder nächste kommende Saison; `null` = aktuell keine Saison (alle abgelaufen/abgeschlossen). */
        public ?string $seasonName,
        public ?\DateTimeImmutable $seasonStartsOn,
    ) {
    }
}
