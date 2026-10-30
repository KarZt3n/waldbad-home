<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Dto;

readonly class ExportWorkAssignmentRefundsRequest
{
    /**
     * @param list<string> $primaryMemberNumbers die in der Übersicht als überweisbar angezeigten
     *                                           Haushalte — schützt davor, dass sich der Datenstand
     *                                           seit dem Öffnen geändert hat und unbemerkt andere
     *                                           Haushalte überwiesen werden
     */
    public function __construct(
        public int $year,
        public \DateTimeImmutable $executionDate,
        public array $primaryMemberNumbers,
    ) {
    }
}
