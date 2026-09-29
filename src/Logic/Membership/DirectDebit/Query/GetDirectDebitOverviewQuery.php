<?php

namespace App\Logic\Membership\DirectDebit\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\DirectDebit\Dto\DirectDebitOverviewEntryResponse;
use App\Logic\Membership\DirectDebit\Dto\DirectDebitOverviewResponse;
use App\Logic\Membership\DirectDebit\Model\DirectDebitObstacleKind;
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

    public function execute(): DirectDebitOverviewResponse
    {
        $invalid = [];
        $valid = [];
        $withoutAmount = [];
        foreach ($this->planner->planAll($this->clock->now()) as $draft) {
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
        foreach (DirectDebitObstacleKind::cases() as $kind) {
            $count = count(array_filter(
                $invalid,
                static fn (DirectDebitOverviewEntryResponse $entry): bool => in_array($kind->value, array_column($entry->obstacles, 'kind'), true),
            ));
            if ($count > 0) {
                $categories[] = ['kind' => $kind->value, 'label' => $kind->label(), 'count' => $count];
            }
        }

        return new DirectDebitOverviewResponse($invalid, $valid, $withoutAmount, $categories);
    }
}
