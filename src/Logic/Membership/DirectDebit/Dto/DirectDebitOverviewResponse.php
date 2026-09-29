<?php

namespace App\Logic\Membership\DirectDebit\Dto;

readonly class DirectDebitOverviewResponse
{
    /**
     * @param list<DirectDebitOverviewEntryResponse> $invalid              nicht im Sammelexport (siehe `$obstacles`)
     * @param list<DirectDebitOverviewEntryResponse> $valid                im Sammelexport enthalten
     * @param list<DirectDebitOverviewEntryResponse> $withoutAmount        Zahler, bei denen mit den Vorgaben der
     *                                                                     Einzelvorschau nichts abzubuchen ist
     *                                                                     (z. B. beitragsfrei) — nicht im Sammelexport
     * @param list<array{kind: string, label: string, count: int}> $obstacleCategories die in `$invalid`
     *                                                                     vorkommenden Kategorien (siehe
     *                                                                     `DirectDebitObstacleKind`) mit der
     *                                                                     Anzahl betroffener Zahler — ein
     *                                                                     Zahler kann in mehreren zählen
     */
    public function __construct(
        public array $invalid,
        public array $valid,
        public array $withoutAmount,
        public array $obstacleCategories,
    ) {
    }
}
