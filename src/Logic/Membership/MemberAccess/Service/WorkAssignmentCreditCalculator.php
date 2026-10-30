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
        return $this->calculateForPeriod($householdMembers, $this->config->periodFrom(), $this->config->periodTo());
    }

    /**
     * Wie `calculate()`, aber für einen frei gewählten Zeitraum [$periodFrom, $periodTo) — z. B. ein
     * zurückliegendes Beitragsjahr in der Mitgliederverwaltung. Zuschlagspflicht und Betrag des
     * Zuschlags werden dabei aus dem heutigen Stand übernommen, da es keine Beitragshistorie je Jahr
     * gibt; die benötigten Stunden richten sich nach dem Stichtag des Zeitraums.
     *
     * @param list<Member> $householdMembers
     */
    public function calculateForPeriod(array $householdMembers, \DateTimeImmutable $periodFrom, \DateTimeImmutable $periodTo): WorkAssignmentCreditResponse
    {
        $memberIds = array_map(static fn (Member $member): string => $member->id, $householdMembers);

        return $this->build(
            $householdMembers,
            $periodFrom,
            $periodTo,
            $this->creditPerHourCents($periodFrom),
            $this->workedMinutesQuery->totalMinutes($memberIds, $periodFrom, $periodTo),
        );
    }

    /**
     * Wie `calculateForPeriod()` für viele Haushalte auf einmal (z. B. die Gutschrift-Überweisung an
     * alle Haushalte) — Stunden und Beitragssatz werden dafür nur einmal statt je Haushalt geladen.
     *
     * @param array<string, list<Member>> $households
     *
     * @return array<string, WorkAssignmentCreditResponse> mit denselben Schlüsseln wie `$households`
     */
    public function calculateForHouseholds(array $households, \DateTimeImmutable $periodFrom, \DateTimeImmutable $periodTo): array
    {
        $memberIds = [];
        foreach ($households as $householdMembers) {
            foreach ($householdMembers as $member) {
                $memberIds[] = $member->id;
            }
        }
        $minutesByMember = $memberIds === [] ? [] : $this->workedMinutesQuery->minutesByMember($memberIds, $periodFrom, $periodTo);
        $creditPerHourCents = $this->creditPerHourCents($periodFrom);

        $credits = [];
        foreach ($households as $key => $householdMembers) {
            $workedMinutes = array_sum(array_map(
                static fn (Member $member): int => $minutesByMember[$member->id] ?? 0,
                $householdMembers,
            ));
            $credits[$key] = $this->build($householdMembers, $periodFrom, $periodTo, $creditPerHourCents, $workedMinutes);
        }

        return $credits;
    }

    private function creditPerHourCents(\DateTimeImmutable $periodFrom): int
    {
        // Die Stundenzahl richtet sich nach dem Beginn des Zeitraums, nicht nach "heute" — sie soll
        // widerspiegeln, was für den Zuschlag galt, der in genau diesem Zeitraum berechnet wurde.
        $requiredHours = $this->config->requiredHoursAt($periodFrom);
        $surchargeRate = $this->rates->findByCategory(ContributionCategory::WorkAssignmentSurcharge);

        return $surchargeRate !== null && $requiredHours > 0
            ? (int) round($surchargeRate->annualAmountCents() / $requiredHours)
            : 0;
    }

    /**
     * @param list<Member> $householdMembers
     */
    private function build(array $householdMembers, \DateTimeImmutable $periodFrom, \DateTimeImmutable $periodTo, int $creditPerHourCents, int $workedMinutes): WorkAssignmentCreditResponse
    {
        $liableMembers = array_values(array_filter(
            $householdMembers,
            static fn (Member $member): bool => $member->workAssignmentSurchargeCents !== null,
        ));
        $totalSurchargeCents = array_sum(array_map(
            static fn (Member $member): int => $member->workAssignmentSurchargeCents ?? 0,
            $liableMembers,
        ));

        $creditCents = min(
            (int) round($workedMinutes * $creditPerHourCents / 60),
            $totalSurchargeCents,
        );

        return new WorkAssignmentCreditResponse(
            periodFrom: $periodFrom->format('Y-m-d'),
            periodTo: $periodTo->format('Y-m-d'),
            liableMemberCount: count($liableMembers),
            totalSurchargeCents: $totalSurchargeCents,
            requiredHoursPerAssignment: $this->config->requiredHoursAt($periodFrom),
            creditPerHourCents: $creditPerHourCents,
            workedMinutes: $workedMinutes,
            creditCents: $creditCents,
        );
    }
}
