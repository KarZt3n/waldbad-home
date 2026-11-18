<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Dto;

readonly class ExportWorkAssignmentRefundsRequest
{
    /**
     * @param list<string> $payerMemberNumbers die in der Übersicht als überweisbar angezeigten
     *                                         Zahler-Stränge — schützt davor, dass sich der
     *                                         Datenstand seit dem Öffnen geändert hat und unbemerkt
     *                                         andere überwiesen werden
     */
    public function __construct(
        public int $year,
        public \DateTimeImmutable $executionDate,
        public array $payerMemberNumbers,
    ) {
    }
}
