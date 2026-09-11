<?php

namespace App\Logic\Membership\MemberAccess\Dto;

/**
 * Die für „Meine Mitgliedschaft" berechnete Arbeitseinsatz-Gutschrift des gesamten Haushalts (siehe
 * `WorkAssignmentCreditCalculator`) — eine gesonderte Rückzahlung, die **nicht** von der
 * Gesamtberechnung (`MemberAccessSessionResponse`) abgezogen wird.
 */
readonly class WorkAssignmentCreditResponse
{
    public function __construct(
        public string $periodFrom,
        public string $periodTo,
        /** Anzahl der Haushaltsmitglieder, die den Arbeitseinsatz-Zuschlag zahlen ("X Arbeitseinsätze"). */
        public int $liableMemberCount,
        /** Summe des Arbeitseinsatz-Zuschlags dieser Mitglieder — zugleich Obergrenze der Gutschrift. */
        public int $totalSurchargeCents,
        public int $requiredHoursPerAssignment,
        public int $creditPerHourCents,
        /** Über die ganze Familie aufsummierte geleistete Minuten im Zeitraum (Stunden sind innerhalb der Familie übertragbar). */
        public int $workedMinutes,
        public int $creditCents,
    ) {
    }
}
