<?php

namespace App\Logic\Membership\WorkAssignmentRefund\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\WorkAssignmentRefund\Dto\ExportWorkAssignmentRefundsRequest;
use App\Logic\Membership\WorkAssignmentRefund\Dto\WorkAssignmentRefundFileResponse;
use App\Logic\Membership\WorkAssignmentRefund\Service\WorkAssignmentRefundExporter;
use App\Logic\Membership\WorkAssignmentRefund\Service\WorkAssignmentRefundPlanner;

/**
 * Sammelüberweisung: eine SEPA-Überweisungsdatei mit der Arbeitsstunden-Gutschrift aller
 * überweisbaren Haushalte eines Beitragsjahres (siehe `GetWorkAssignmentRefundOverviewQuery`). Jeder
 * Haushalt wird in der Gutschrift-Historie festgehalten, damit dasselbe Jahr nicht doppelt
 * erstattet wird.
 */
readonly class ExportWorkAssignmentRefundsUseCase
{
    public function __construct(
        private WorkAssignmentRefundPlanner $planner,
        private WorkAssignmentRefundExporter $exporter,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ExportWorkAssignmentRefundsRequest $request): WorkAssignmentRefundFileResponse
    {
        $now = $this->clock->now();
        $exportable = [];
        foreach ($this->planner->planAll($request->year, $now) as $draft) {
            if ($draft->isExportable()) {
                $exportable[$draft->primaryMemberNumber] = $draft;
            }
        }
        $requested = array_values(array_unique($request->primaryMemberNumbers));
        $available = array_map(strval(...), array_keys($exportable));
        sort($requested);
        sort($available);
        if ($requested !== $available) {
            throw new BusinessRuleViolationException('Die Gutschrift-Übersicht ist nicht mehr aktuell. Bitte die Übersicht neu öffnen.');
        }
        $drafts = array_values($exportable);
        if ($drafts === []) {
            throw new BusinessRuleViolationException('Es gibt keine überweisbaren Gutschriften.');
        }

        return $this->exporter->export($drafts, $request->executionDate, $now, 'sammel');
    }
}
