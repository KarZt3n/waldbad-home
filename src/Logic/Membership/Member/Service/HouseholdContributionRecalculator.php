<?php

namespace App\Logic\Membership\Member\Service;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;

/**
 * Berechnet den Beitrag nicht nur für ein einzelnes Mitglied neu, sondern für dessen gesamten
 * Haushalt (alle Mitglieder mit derselben Hauptnummer) und speichert alle Ergebnisse: Ob der
 * Familienrabatt greift, hängt vom gesamten Haushalt ab (ein qualifizierendes Kind, dessen
 * Geburtsreihenfolge unter den Geschwistern), sodass die Neuberechnung eines Mitglieds auch die
 * Berechnung der übrigen Haushaltsmitglieder verändern kann. Wird sowohl vom manuellen
 * „Beitrag neu berechnen“ (`RecalculateMemberContributionUseCase`) als auch automatisch beim
 * Wechsel der Funktion auf/von „Vorstand“ (`UpdateMemberUseCase`) verwendet.
 *
 * Vor der eigentlichen Berechnung korrigiert `BoardFamilyExemptionResolver` jedes Haushaltsmitglied:
 * Ist mindestens ein Haushaltsmitglied Vorstand, gilt für den gesamten Haushalt keine
 * Beitragspflicht. Scheitert diese Korrektur für ein Mitglied (z. B. fehlende/ungültige IBAN bei
 * einer Reaktivierung), wird die Fehlermeldung um Name und Mitgliedsnummer dieses Mitglieds ergänzt
 * — auch wenn es nicht das angefragte, sondern ein Geschwister im selben Haushalt ist, damit klar
 * ist, wen die Meldung betrifft.
 */
readonly class HouseholdContributionRecalculator
{
    public function __construct(
        private MemberManagerInterface $manager,
        private MemberContributionCalculator $calculator,
        private ClockInterface $clock,
        private BoardFamilyExemptionResolver $exemption,
    ) {
    }

    public function recalculate(Member $member): Member
    {
        $household = $this->manager->findByPrimaryMemberNumber($member->primaryMemberNumber);
        $hasBoardMember = $this->exemption->hasBoardMember($household);
        $now = $this->clock->now();

        $result = $member;
        foreach ($household as $candidate) {
            $otherHouseholdMembers = array_values(array_filter(
                $household,
                static fn (Member $other): bool => $other->id !== $candidate->id,
            ));
            try {
                $candidate = $this->exemption->correct($candidate, $hasBoardMember);
                $outcome = $this->calculator->calculate($candidate, $otherHouseholdMembers, $now);
                $saved = $this->manager->save($candidate->withContribution($outcome->category, $outcome->amountCents, $outcome->workAssignmentSurchargeCents));
            } catch (BusinessRuleViolationException $exception) {
                throw new BusinessRuleViolationException(sprintf(
                    '%s %s (%s): %s',
                    $candidate->firstName,
                    $candidate->lastName,
                    $candidate->memberNumber,
                    $exception->getMessage(),
                ), previous: $exception);
            }
            if ($saved->id === $member->id) {
                $result = $saved;
            }
        }

        // $household enthält $member immer selbst (die eigene Hauptnummer verweist im
        // Selbst-/Kopf-Fall auf sich selbst), daher wurde $result oben stets aktualisiert.
        return $result;
    }
}
