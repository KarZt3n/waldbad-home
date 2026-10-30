<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\WorkAssignment\MemberWorkAssignmentProviderInterface;
use App\Logic\Membership\WorkAssignment\Model\WorkAssignmentParticipation;
use App\Logic\Membership\WorkAssignmentRefund\Dto\WorkAssignmentRefundDetailResponse;
use App\Logic\Membership\WorkAssignmentRefund\Dto\WorkAssignmentRefundMemberResponse;
use App\Logic\Membership\WorkAssignmentRefund\Dto\WorkAssignmentRefundOverviewEntryResponse;
use App\Logic\Membership\WorkAssignmentRefund\Manager\WorkAssignmentRefundRecordManagerInterface;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;
use App\Logic\Membership\WorkAssignmentRefund\Service\WorkAssignmentRefundPlanner;

/**
 * Gutschrift eines Haushalts im Detail: Empfänger, Vereinskonto, je Mitglied Zuschlag und die
 * Einsätze des Beitragsjahres (dieselbe Quelle wie bei den Arbeitseinsätzen des Mitglieds, siehe
 * `GetMemberWorkAssignmentsQuery`) sowie die bisherigen Überweisungen.
 */
readonly class GetWorkAssignmentRefundDetailQuery
{
    public function __construct(
        private WorkAssignmentRefundPlanner $planner,
        private MemberWorkAssignmentProviderInterface $workAssignments,
        private WorkAssignmentRefundRecordManagerInterface $records,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $primaryMemberNumber, ?int $year): WorkAssignmentRefundDetailResponse
    {
        $now = $this->clock->now();
        $year ??= (int) $now->format('Y');
        $draft = $this->planner->plan($primaryMemberNumber, $year, $now);

        $participationsByMember = [];
        $memberIds = array_map(static fn (Member $member): string => $member->id, $draft->householdMembers);
        foreach ($this->workAssignments->findForMembers($memberIds) as $participation) {
            if ($participation->year() === $year) {
                $participationsByMember[$participation->memberId][] = $participation;
            }
        }

        $members = [];
        foreach ($draft->householdMembers as $member) {
            $participations = $participationsByMember[$member->id] ?? [];
            $members[] = new WorkAssignmentRefundMemberResponse(
                memberId: $member->id,
                memberNumber: $member->memberNumber,
                name: $member->firstName.' '.$member->lastName,
                surchargeCents: $member->workAssignmentSurchargeCents,
                workedMinutes: array_sum(array_map(static fn (WorkAssignmentParticipation $participation): int => $participation->minutes, $participations)),
                participations: $participations,
            );
        }
        // Wie in der Lastschrift-Vorschau: Mitglieder mit Zuschlag oder Einsätzen zuerst, dann nach Nummer.
        usort($members, static fn (WorkAssignmentRefundMemberResponse $left, WorkAssignmentRefundMemberResponse $right): int => [$right->surchargeCents !== null || $right->workedMinutes > 0, $left->memberNumber] <=> [$left->surchargeCents !== null || $left->workedMinutes > 0, $right->memberNumber]);

        return new WorkAssignmentRefundDetailResponse(
            year: $year,
            defaultExecutionDate: $this->planner->defaultExecutionDate($now)->format('Y-m-d'),
            entry: WorkAssignmentRefundOverviewEntryResponse::fromDraft($draft),
            bankName: $draft->recipient?->bankName,
            creditPerHourCents: $draft->credit->creditPerHourCents,
            totalSurchargeCents: $draft->credit->totalSurchargeCents,
            clubAccount: $this->planner->clubAccount(),
            members: $members,
            refunds: array_values(array_filter(
                $this->records->findByYear($year),
                static fn (WorkAssignmentRefundRecord $record): bool => $record->primaryMemberNumber === $primaryMemberNumber,
            )),
        );
    }
}
