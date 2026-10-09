<?php

namespace App\Logic\Membership\WorkAssignment\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\MemberAccess\Service\WorkAssignmentCreditCalculator;
use App\Logic\Membership\WorkAssignment\Dto\MemberWorkAssignmentYearResponse;
use App\Logic\Membership\WorkAssignment\MemberWorkAssignmentProviderInterface;
use App\Logic\Membership\WorkAssignment\Model\WorkAssignmentParticipation;

/**
 * Arbeitseinsätze eines Mitglieds je Beitragsjahr (Kalenderjahr) für die Mitgliederverwaltung —
 * wie in „Meine Mitgliedschaft“ über den ganzen Haushalt, da Stunden innerhalb der Familie
 * übertragbar sind (siehe `WorkAssignmentCreditCalculator`). Enthält das laufende Jahr immer, frühere
 * Jahre nur, wenn in ihnen Stunden geleistet wurden; neuestes Jahr zuerst.
 */
readonly class GetMemberWorkAssignmentsQuery
{
    public function __construct(
        private MemberManagerInterface $members,
        private MemberWorkAssignmentProviderInterface $workAssignments,
        private WorkAssignmentCreditCalculator $creditCalculator,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<MemberWorkAssignmentYearResponse> */
    public function execute(string $memberId): array
    {
        $household = $this->members->findByPrimaryMemberNumber($this->members->get($memberId)->primaryMemberNumber);
        $memberNames = [];
        foreach ($household as $member) {
            $memberNames[$member->id] = $member->firstName.' '.$member->lastName;
        }
        $participations = $this->workAssignments->findForMembers(array_map(static fn (Member $member): string => $member->id, $household));

        $participationsByYear = [(int) $this->clock->now()->format('Y') => []];
        foreach ($participations as $participation) {
            $participationsByYear[$participation->year()][] = $participation;
        }
        krsort($participationsByYear);

        $years = [];
        foreach ($participationsByYear as $year => $yearParticipations) {
            $periodFrom = new \DateTimeImmutable(sprintf('%04d-01-01', $year));
            $years[] = new MemberWorkAssignmentYearResponse(
                year: $year,
                credit: $this->creditCalculator->calculateForPeriod($household, $periodFrom, $periodFrom->modify('+1 year')),
                participations: $yearParticipations,
                memberNames: $memberNames,
            );
        }

        return $years;
    }
}
