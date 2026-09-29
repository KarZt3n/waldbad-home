<?php

namespace App\Logic\Membership\Dashboard\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Dashboard\Dto\ContributionRateCount;
use App\Logic\Membership\Dashboard\Dto\MembershipDashboardResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\PayerType;

/**
 * Bewusst schlanker Platzhalter für das Mitgliederverwaltungs-Dashboard — liefert nur die
 * wichtigsten Kennzahlen. Wird schrittweise um weitere Auswertungen ergänzt.
 */
readonly class GetMembershipDashboardQuery
{
    public function __construct(
        private MemberManagerInterface $members,
        private MembershipApplicationManagerInterface $applications,
        private ContributionRateManagerInterface $contributionRates,
        private ClockInterface $clock,
    ) {
    }

    public function execute(): MembershipDashboardResponse
    {
        $members = $this->members->search(null);
        // „Offen“ heißt: über den Antrag wurde noch nicht entschieden (weder als Mitglied
        // angelegt noch abgelehnt).
        $pendingApplications = array_filter(
            $this->applications->list(),
            static fn (MembershipApplication $application): bool => $application->releasedAt === null && $application->rejectedAt === null,
        );

        $totalContributionCents = (int) array_sum(array_map(
            static fn (Member $member): int => ($member->contributionAmountCents ?? 0) + ($member->workAssignmentSurchargeCents ?? 0),
            $members,
        ));

        $now = $this->clock->now();
        $currentYear = (int) $now->format('Y');
        $activeMembers = array_values(array_filter(
            $members,
            static fn (Member $member): bool => $member->isActive($now),
        ));
        $householdSizes = array_count_values(array_map(
            static fn (Member $member): string => $member->primaryMemberNumber,
            $activeMembers,
        ));
        $adults = count(array_filter(
            $activeMembers,
            static fn (Member $member): bool => $member->ageAt($now) >= 21,
        ));

        return new MembershipDashboardResponse(
            totalMembers: count($members),
            pendingApplications: count($pendingApplications),
            totalContributionCents: $totalContributionCents,
            leavingAtYearEnd: $this->countLeavingAtYearEnd($members, $currentYear),
            leftLastYearEnd: $this->countLeavingAtYearEnd($members, $currentYear - 1),
            payers: count(array_filter($members, static fn (Member $member): bool => $member->payerType === PayerType::SelfPayer)),
            families: count(array_filter(
                $activeMembers,
                static fn (Member $member): bool => $member->payerType === PayerType::SelfPayer && $householdSizes[$member->primaryMemberNumber] > 1,
            )),
            individualMemberships: count(array_filter(
                $activeMembers,
                static fn (Member $member): bool => $member->payerType === PayerType::SelfPayer && $householdSizes[$member->primaryMemberNumber] === 1,
            )),
            adults: $adults,
            minors: count($activeMembers) - $adults,
            contributionRateCounts: $this->countByContributionRate($activeMembers),
        );
    }

    /**
     * @param list<Member> $members
     */
    private function countLeavingAtYearEnd(array $members, int $year): int
    {
        $yearEnd = sprintf('%d-12-31', $year);

        return count(array_filter(
            $members,
            static fn (Member $member): bool => $member->leftAt !== null && $member->leftAt->format('Y-m-d') === $yearEnd,
        ));
    }

    /**
     * Je Beitragssatz mit fester Kategorie (in der Reihenfolge der Beitragssatz-Liste) die Zahl der
     * aktiven Mitglieder, denen er zugeordnet ist. Der Arbeitseinsatz-Zuschlag ist keine eigene
     * Beitragskategorie eines Mitglieds, sondern ein zusätzlicher Betrag — er zählt daher alle
     * Mitglieder, für die ein Zuschlag berechnet ist. Frei angelegte Sätze ohne Kategorie (z. B.
     * Beitrittsgebühren) werden keinem Mitglied dauerhaft zugeordnet und fehlen deshalb.
     *
     * @param list<Member> $activeMembers
     *
     * @return list<ContributionRateCount>
     */
    private function countByContributionRate(array $activeMembers): array
    {
        $counts = [];
        foreach ($this->contributionRates->list() as $rate) {
            if ($rate->category === null) {
                continue;
            }
            $counts[] = new ContributionRateCount($rate->label, count(array_filter(
                $activeMembers,
                static fn (Member $member): bool => $rate->category === ContributionCategory::WorkAssignmentSurcharge
                    ? $member->workAssignmentSurchargeCents !== null
                    : $member->contributionCategory === $rate->category,
            )));
        }

        $withoutRate = array_filter(
            $activeMembers,
            static fn (Member $member): bool => $member->contributionCategory === null,
        );
        $notLiable = count(array_filter($withoutRate, static fn (Member $member): bool => !$member->contributionLiable));
        if ($notLiable > 0) {
            $counts[] = new ContributionRateCount('Ohne Beitragssatz (nicht beitragspflichtig)', $notLiable);
        }
        if (count($withoutRate) > $notLiable) {
            $counts[] = new ContributionRateCount('Ohne Beitragssatz', count($withoutRate) - $notLiable);
        }

        return $counts;
    }
}
