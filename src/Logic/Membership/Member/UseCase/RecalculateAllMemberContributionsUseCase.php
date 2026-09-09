<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Dto\MemberRecalculationError;
use App\Logic\Membership\Member\Dto\RecalculateAllContributionsResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Service\BoardFamilyExemptionResolver;
use App\Logic\Membership\Member\Service\MemberContributionCalculator;

/**
 * Berechnet den Beitrag für alle Mitglieder neu — z. B. nachdem Beitragssätze geändert wurden.
 * Jeder Haushalt (Mitglieder mit derselben Hauptnummer) wird dabei nur einmal anhand desselben
 * Snapshots durchgerechnet, nicht einmal pro Mitglied darin (siehe
 * `RecalculateMemberContributionUseCase`, dessen Haushaltslogik dieselbe ist, nur für ein
 * einzelnes angefragtes Mitglied statt für den gesamten Bestand).
 *
 * Vor der eigentlichen Berechnung korrigiert `BoardFamilyExemptionResolver` jedes Haushaltsmitglied:
 * Ist mindestens ein Haushaltsmitglied Vorstand, gilt für den gesamten Haushalt keine
 * Beitragspflicht. Diese Korrektur läuft bewusst innerhalb derselben Fehlerbehandlung wie die
 * eigentliche Berechnung (nicht vorab für den ganzen Haushalt auf einmal): Scheitert sie für ein
 * einzelnes Mitglied (z. B. fehlende/ungültige IBAN bei einer Reaktivierung), wird nur dieser
 * Datensatz übersprungen und mit seiner Mitgliedsnummer gemeldet, statt den gesamten Lauf
 * abzubrechen.
 *
 * Ein fehlender Beitragssatz für einzelne Mitglieder (z. B. eine gelöschte Kategorie) bricht den
 * gesamten Lauf nicht ab — der betroffene Datensatz wird übersprungen und als Fehler gemeldet,
 * alle anderen werden trotzdem aktualisiert.
 */
readonly class RecalculateAllMemberContributionsUseCase
{
    public function __construct(
        private MemberManagerInterface $manager,
        private MemberContributionCalculator $calculator,
        private ClockInterface $clock,
        private BoardFamilyExemptionResolver $exemption,
    ) {
    }

    public function execute(): RecalculateAllContributionsResponse
    {
        $now = $this->clock->now();

        /** @var array<string, list<Member>> $households */
        $households = [];
        foreach ($this->manager->search(null) as $member) {
            $households[$member->primaryMemberNumber][] = $member;
        }

        $updated = 0;
        $errors = [];
        foreach ($households as $household) {
            $hasBoardMember = $this->exemption->hasBoardMember($household);
            foreach ($household as $candidate) {
                $otherHouseholdMembers = array_values(array_filter(
                    $household,
                    static fn (Member $other): bool => $other->id !== $candidate->id,
                ));
                try {
                    $candidate = $this->exemption->correct($candidate, $hasBoardMember);
                    $outcome = $this->calculator->calculate($candidate, $otherHouseholdMembers, $now);
                    $this->manager->save($candidate->withContribution($outcome->category, $outcome->amountCents, $outcome->workAssignmentSurchargeCents));
                    ++$updated;
                } catch (BusinessRuleViolationException $exception) {
                    $errors[] = new MemberRecalculationError($candidate->memberNumber, $exception->getMessage());
                }
            }
        }

        return new RecalculateAllContributionsResponse($updated, $errors);
    }
}
