<?php

namespace App\Logic\Membership\MemberAccess\Service;

use App\Logic\Event\HelpRequest\Query\GetMemberWorkedMinutesQuery;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\MemberAccess\Dto\WorkAssignmentCreditResponse;

/**
 * Ermittelt die Arbeitseinsatz-Gutschrift für „Meine Mitgliedschaft" (siehe
 * `ResolveMemberAccessSessionUseCase`): Welche Haushaltsmitglieder laut ihrer Altersspanne den
 * Beitragssatz „Arbeitseinsatz-Zuschlag" zahlen ("X Arbeitseinsätze", siehe
 * `Member::$workAssignmentSurchargeCents`/`MemberContributionCalculator`) und wie viel das in Summe
 * ist, wie viele Stunden die ganze Familie im konfigurierten Zeitraum
 * (`WorkAssignmentCreditConfig::periodFrom()`/`periodTo()`) über die „Ich möchte Helfen!"-Funktion
 * geleistet hat — Stunden sind innerhalb der Familie übertragbar (ein Kind kann z. B. keine Stunden
 * leisten, wenn dafür ein Elternteil mehr leistet) — und wie hoch die daraus resultierende
 * Gutschrift ist. Die Gutschrift kann den tatsächlich fälligen Arbeitseinsatz-Zuschlag der Familie
 * nie übersteigen (kein Zuschuss zum normalen Mitgliedsbeitrag, keine Auszahlung darüber hinaus)
 * und wird **nicht** von der Gesamtberechnung abgezogen, sondern als gesonderte Rückzahlung
 * ausgewiesen (Nutzer-Entscheidung).
 */
readonly class WorkAssignmentCreditCalculator
{
    public function __construct(
        private ContributionRateManagerInterface $rates,
        private GetMemberWorkedMinutesQuery $workedMinutesQuery,
        private WorkAssignmentCreditConfig $config,
    ) {
    }

    /**
     * @param list<Member> $householdMembers
     */
    public function calculate(array $householdMembers): WorkAssignmentCreditResponse
    {
        $liableMembers = array_values(array_filter(
            $householdMembers,
            static fn (Member $member): bool => $member->workAssignmentSurchargeCents !== null,
        ));
        $totalSurchargeCents = array_sum(array_map(
            static fn (Member $member): int => $member->workAssignmentSurchargeCents ?? 0,
            $liableMembers,
        ));

        $periodFrom = $this->config->periodFrom();
        $periodTo = $this->config->periodTo();
        // Die Stundenzahl richtet sich nach dem Beginn des Zeitraums, nicht nach "heute" — sie soll
        // widerspiegeln, was für den Zuschlag galt, der in genau diesem Zeitraum berechnet wurde.
        $requiredHours = $this->config->requiredHoursAt($periodFrom);

        $surchargeRate = $this->rates->findByCategory(ContributionCategory::WorkAssignmentSurcharge);
        $creditPerHourCents = $surchargeRate !== null && $requiredHours > 0
            ? (int) round($surchargeRate->annualAmountCents() / $requiredHours)
            : 0;

        $memberIds = array_map(static fn (Member $member): string => $member->id, $householdMembers);
        $workedMinutes = $this->workedMinutesQuery->totalMinutes($memberIds, $periodFrom, $periodTo);

        $creditCents = min(
            (int) round($workedMinutes * $creditPerHourCents / 60),
            $totalSurchargeCents,
        );

        return new WorkAssignmentCreditResponse(
            periodFrom: $periodFrom->format('Y-m-d'),
            periodTo: $periodTo->format('Y-m-d'),
            liableMemberCount: count($liableMembers),
            totalSurchargeCents: $totalSurchargeCents,
            requiredHoursPerAssignment: $requiredHours,
            creditPerHourCents: $creditPerHourCents,
            workedMinutes: $workedMinutes,
            creditCents: $creditCents,
        );
    }
}
