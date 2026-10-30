<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\WorkAssignmentRefund\Dto\WorkAssignmentRefundOverviewEntryResponse;
use App\Logic\Membership\WorkAssignmentRefund\Dto\WorkAssignmentRefundOverviewResponse;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundObstacleKind;
use App\Logic\Membership\WorkAssignmentRefund\Service\WorkAssignmentRefundPlanner;

/**
 * Gutschrift-Übersicht eines Beitragsjahres über alle Haushalte mit geleisteten Arbeitsstunden,
 * getrennt nach „nicht überweisbar“ (mit Grund), „überweisbar“ und „nichts zu überweisen“ —
 * dieselbe Einteilung, die die Sammelüberweisung (`ExportWorkAssignmentRefundsUseCase`) verwendet.
 */
readonly class GetWorkAssignmentRefundOverviewQuery
{
    public function __construct(
        private WorkAssignmentRefundPlanner $planner,
        private ClockInterface $clock,
    ) {
    }

    public function execute(?int $year): WorkAssignmentRefundOverviewResponse
    {
        $now = $this->clock->now();
        $year ??= (int) $now->format('Y');
        $invalid = [];
        $valid = [];
        $withoutAmount = [];
        foreach ($this->planner->planAll($year, $now) as $draft) {
            $entry = WorkAssignmentRefundOverviewEntryResponse::fromDraft($draft);
            if ($draft->amountCents() <= 0) {
                $withoutAmount[] = $entry;
            } elseif ($draft->isExportable()) {
                $valid[] = $entry;
            } else {
                $invalid[] = $entry;
            }
        }

        $categories = [];
        foreach (WorkAssignmentRefundObstacleKind::cases() as $kind) {
            $count = count(array_filter(
                $invalid,
                static fn (WorkAssignmentRefundOverviewEntryResponse $entry): bool => in_array($kind->value, array_column($entry->obstacles, 'kind'), true),
            ));
            if ($count > 0) {
                $categories[] = ['kind' => $kind->value, 'label' => $kind->label(), 'count' => $count];
            }
        }

        return new WorkAssignmentRefundOverviewResponse(
            year: $year,
            selectableYears: $this->planner->selectableYears($now),
            defaultExecutionDate: $this->planner->defaultExecutionDate($now)->format('Y-m-d'),
            invalid: $invalid,
            valid: $valid,
            withoutAmount: $withoutAmount,
            obstacleCategories: $categories,
        );
    }
}
