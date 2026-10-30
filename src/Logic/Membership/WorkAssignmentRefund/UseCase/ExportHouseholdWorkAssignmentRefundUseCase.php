<?php

namespace App\Logic\Membership\WorkAssignmentRefund\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\WorkAssignmentRefund\Dto\ExportHouseholdWorkAssignmentRefundRequest;
use App\Logic\Membership\WorkAssignmentRefund\Dto\WorkAssignmentRefundFileResponse;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundObstacle;
use App\Logic\Membership\WorkAssignmentRefund\Service\WorkAssignmentRefundExporter;
use App\Logic\Membership\WorkAssignmentRefund\Service\WorkAssignmentRefundPlanner;

/**
 * Einzelüberweisung der Arbeitsstunden-Gutschrift für einen Haushalt (aus der Detailansicht, z. B.
 * vom Reiter „Arbeitseinsätze“ eines Mitglieds) — mit derselben Berechnung und Historie wie die
 * Sammelüberweisung.
 */
readonly class ExportHouseholdWorkAssignmentRefundUseCase
{
    public function __construct(
        private WorkAssignmentRefundPlanner $planner,
        private WorkAssignmentRefundExporter $exporter,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ExportHouseholdWorkAssignmentRefundRequest $request): WorkAssignmentRefundFileResponse
    {
        $now = $this->clock->now();
        $draft = $this->planner->plan($request->primaryMemberNumber, $request->year, $now);
        if ($draft->obstacles !== []) {
            throw new BusinessRuleViolationException(implode(' ', array_map(static fn (WorkAssignmentRefundObstacle $obstacle): string => $obstacle->message, $draft->obstacles)));
        }
        if (!$draft->isExportable()) {
            throw new BusinessRuleViolationException('Für diesen Haushalt ist nichts (mehr) zu überweisen.');
        }

        return $this->exporter->export([$draft], $request->executionDate, $now, $draft->primaryMemberNumber);
    }
}
