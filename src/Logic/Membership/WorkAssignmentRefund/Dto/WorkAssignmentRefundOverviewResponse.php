<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Dto;

readonly class WorkAssignmentRefundOverviewResponse
{
    /**
     * @param list<int>                                          $selectableYears    laufendes Jahr zuerst
     * @param list<WorkAssignmentRefundOverviewEntryResponse>   $invalid            nicht in der Sammelüberweisung (siehe `$obstacles`)
     * @param list<WorkAssignmentRefundOverviewEntryResponse>   $valid              in der Sammelüberweisung enthalten
     * @param list<WorkAssignmentRefundOverviewEntryResponse>   $withoutAmount      Stunden geleistet, aber nichts (mehr)
     *                                                                              zu überweisen — kein Zuschlag oder
     *                                                                              bereits erstattet
     * @param list<array{kind: string, label: string, count: int}> $obstacleCategories die in `$invalid` vorkommenden
     *                                                                              Kategorien mit Anzahl der Haushalte
     */
    public function __construct(
        public int $year,
        public array $selectableYears,
        public string $defaultExecutionDate,
        public array $invalid,
        public array $valid,
        public array $withoutAmount,
        public array $obstacleCategories,
    ) {
    }
}
