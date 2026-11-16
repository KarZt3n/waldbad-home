<?php

namespace App\Logic\Membership\DirectDebit\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\DirectDebit\Dto\DirectDebitOverviewEntryResponse;
use App\Logic\Membership\DirectDebit\Dto\DirectDebitOverviewResponse;
use App\Logic\Membership\DirectDebit\Model\DirectDebitObstacleKind;
use App\Logic\Membership\DirectDebit\Model\DirectDebitPositionKind;
use App\Logic\Membership\DirectDebit\Service\PayerDirectDebitPlanner;

/**
 * Lastschrift-Übersicht aller Zahler, getrennt nach „nicht exportierbar“ (mit Grund) und
 * „exportierbar“ — dieselbe Einteilung, die der Sammelexport (`ExportAllDirectDebitsUseCase`)
 * verwendet. Zahler, bei denen mit den Vorgaben der Einzelvorschau nichts abzubuchen ist, werden
 * getrennt aufgeführt.
 */
readonly class GetDirectDebitOverviewQuery
{
    public function __construct(
        private PayerDirectDebitPlanner $planner,
        private ClockInterface $clock,
    ) {
    }

    public function execute(DirectDebitPositionKind $kind = DirectDebitPositionKind::Contribution): DirectDebitOverviewResponse
    {
        $invalid = [];
        $valid = [];
        $withoutAmount = [];
        foreach ($this->planner->planAll($this->clock->now(), $kind) as $draft) {
            $entry = DirectDebitOverviewEntryResponse::fromDraft($draft);
            if ($draft->defaultAmountCents() <= 0) {
                $withoutAmount[] = $entry;
            } elseif ($entry->obstacles === []) {
                $valid[] = $entry;
            } else {
                $invalid[] = $entry;
            }
        }

        $categories = [];
        foreach (DirectDebitObstacleKind::cases() as $obstacleKind) {
            $count = count(array_filter(
                $invalid,
                static fn (DirectDebitOverviewEntryResponse $entry): bool => in_array($obstacleKind->value, array_column($entry->obstacles, 'kind'), true),
            ));
            if ($count > 0) {
                $categories[] = ['kind' => $obstacleKind->value, 'label' => $obstacleKind->label(), 'count' => $count];
            }
        }

        return new DirectDebitOverviewResponse($invalid, $valid, $withoutAmount, $categories);
    }
}
