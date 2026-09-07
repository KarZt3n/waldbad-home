<?php

namespace App\Logic\Membership\Member\Service;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\Member\Dto\ContributionOutcome;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;

/**
 * Ermittelt den Jahresbeitrag eines Mitglieds nach der Beitrags- und Kassenordnung (Anlage 1):
 * Einzelpersonen zahlen altersabhängig, Familienmitgliedschaften erhalten für Elternteile einen
 * Rabatt, aber nur, solange mindestens ein eigenes Kind im Haushalt noch nicht aus der
 * Familien-Kinderpreisung „herausgewachsen“ ist. Kinder außerhalb der beitragsfreien Altersspanne
 * sowie das dritte und jedes weitere Kind der Familie sind beitragsfrei. Zusätzlich zahlen
 * Mitglieder innerhalb der Arbeitseinsatz-Altersspanne einen Zuschlag zzgl. zum eigentlichen
 * Beitrag, der nach Ableistung der Gemeinschaftsstunden separat erstattet wird.
 *
 * Laut Beitrags- und Kassenordnung werden Familienmitglieder nach Vollendung des 21. Lebensjahres
 * ohne fristgemäße Kündigung automatisch Einzelmitglieder: Wächst ein Kind über die für die beiden
 * Kinder-Beitragssätze (`family_child_exempt`/`family_child_paying`) konfigurierte Altersspanne
 * hinaus, wechselt seine eigene Beitragsberechnung automatisch auf den Einzelpersonen-Satz — und
 * dasselbe Kind zählt ab diesem Zeitpunkt auch nicht mehr als Grund für den Familienrabatt der
 * Eltern. Die Hauptnummer und `familyRole` bleiben davon unberührt (die Familienzugehörigkeit an
 * sich endet nicht automatisch); es ändert sich ausschließlich die Beitragsberechnung. Zwei
 * Erwachsene mit derselben Hauptnummer bleiben deshalb „Familie“, erhalten aber keinen
 * Familienrabatt mehr, sobald kein Kind diese Altersspanne mehr erfüllt.
 *
 * Welcher Beitragssatz zutrifft, wird ausschließlich über die bei jedem Beitragssatz hinterlegte
 * Altersspanne (`ContributionRate::$minAge`/`$maxAge`, siehe `appliesToAge()`) entschieden — es
 * gibt dafür keine im Code fest verdrahteten Altersgrenzen mehr. Nur die Reihenfolge-Regel „3. und
 * jedes weitere Kind ist beitragsfrei“ bleibt strukturelle Geschäftslogik ohne eigenen
 * Konfigurationswert, da sie sich auf die Geburtsreihenfolge und nicht auf ein Alter bezieht.
 *
 * Da jeder Beitragssatz (auch die sechs Grundkategorien) gelöscht werden kann, meldet der
 * Rechner über eine fachliche Exception, wenn für die Situation eines Mitglieds kein passender
 * Beitragssatz existiert, statt eine falsche Annahme zu treffen.
 *
 * Ein „Haushalt“ sind alle Mitglieder mit derselben Hauptnummer. Dieser Rechner berechnet immer
 * nur den übergebenen Kandidaten; ob dabei auch Geschwister neu berechnet werden, entscheidet die
 * aufrufende Stelle (bei einer manuellen Neuberechnung im Admin geschieht das für den gesamten
 * Haushalt, siehe `RecalculateMemberContributionUseCase`; bei Neuanlage/Import nur für den
 * jeweiligen Datensatz selbst, um bestehende Mitgliedschaften nicht ungefragt zu verändern).
 */
readonly class MemberContributionCalculator
{
    public function __construct(private ContributionRateManagerInterface $rates)
    {
    }

    /**
     * @param list<Member> $householdMembers Andere Mitglieder mit derselben Hauptnummer wie $candidate (ohne $candidate selbst).
     */
    public function calculate(Member $candidate, array $householdMembers, \DateTimeImmutable $at): ContributionOutcome
    {
        $category = $this->resolveCategory($candidate, $householdMembers, $at);
        $rate = $this->rates->findByCategory($category)
            ?? throw new BusinessRuleViolationException(sprintf(
                'Für die Kategorie "%s" ist kein Beitragssatz hinterlegt. Bitte lege ihn unter „Beitragssätze“ neu an.',
                $category->value,
            ));

        $age = $candidate->age($at);
        $surchargeRate = $this->rates->findByCategory(ContributionCategory::WorkAssignmentSurcharge);
        $surchargeCents = $surchargeRate !== null && $surchargeRate->appliesToAge($age) ? $surchargeRate->annualAmountCents() : null;

        return new ContributionOutcome($category, $rate->annualAmountCents(), $surchargeCents);
    }

    /**
     * @param list<Member> $householdMembers
     */
    private function resolveCategory(Member $candidate, array $householdMembers, \DateTimeImmutable $at): ContributionCategory
    {
        $age = $candidate->age($at);

        if ($candidate->familyRole === FamilyRole::None) {
            return $this->matchIndividualCategory($age)
                ?? throw new BusinessRuleViolationException('Für das Alter dieses Mitglieds ist kein passender Beitragssatz (Einzelperson) hinterlegt.');
        }

        if ($candidate->familyRole === FamilyRole::Head || $candidate->familyRole === FamilyRole::Partner) {
            $familyAdultRate = $this->rates->findByCategory(ContributionCategory::FamilyAdult);
            if ($familyAdultRate !== null && $familyAdultRate->appliesToAge($age) && $this->hasQualifyingChild($householdMembers, $at)) {
                return ContributionCategory::FamilyAdult;
            }

            // Ohne ein eigenes, beitragsberechtigendes Kind (oder ohne passenden Familien-Satz)
            // entfällt der Familienrabatt und es gilt der normale Einzelpersonen-Satz.
            return $this->matchIndividualCategory($age)
                ?? throw new BusinessRuleViolationException('Für das Alter dieses Mitglieds ist kein passender Beitragssatz (Einzelperson/Familie) hinterlegt.');
        }

        // Familienrolle: Kind
        $childExemptRate = $this->rates->findByCategory(ContributionCategory::FamilyChildExempt);
        $childPayingRate = $this->rates->findByCategory(ContributionCategory::FamilyChildPaying);
        $appliesAsChild = ($childExemptRate !== null && $childExemptRate->appliesToAge($age))
            || ($childPayingRate !== null && $childPayingRate->appliesToAge($age));

        if ($appliesAsChild) {
            // Das 3. und jedes weitere Kind ist unabhängig von der Alterstabelle beitragsfrei —
            // aber nur, solange die Person laut Altersspanne überhaupt noch als „Kind" gilt (s. u.).
            if ($this->childOrdinal($candidate, $householdMembers) >= 3) {
                return ContributionCategory::FamilyChildExempt;
            }
            if ($childExemptRate !== null && $childExemptRate->appliesToAge($age)) {
                return ContributionCategory::FamilyChildExempt;
            }

            return ContributionCategory::FamilyChildPaying;
        }

        // Laut Beitrags- und Kassenordnung werden Familienmitglieder nach Vollendung des 21.
        // Lebensjahres ohne fristgemäße Kündigung automatisch Einzelmitglieder: Ist die Person
        // älter als die für Familien-Kinder konfigurierte Altersspanne erlaubt, gilt automatisch
        // der Einzelpersonen-Satz. Die Hauptnummer und Familienzugehörigkeit (`familyRole`) bleiben
        // dabei unverändert bestehen — nur die Beitragsberechnung wechselt auf Einzelmitgliedschaft.
        // Liegt dagegen gar keine Altersspanne vor, in die die Person fallen könnte (z. B. weil
        // beide Kinder-Beitragssätze gelöscht wurden oder sie jünger als jede konfigurierte Spanne
        // ist), bleibt das ein zu meldender Konfigurationsfehler statt einer stillen Annahme.
        if ($this->hasAgedOutOfChildPricing($age, $childExemptRate?->maxAge, $childPayingRate?->maxAge)) {
            return $this->matchIndividualCategory($age)
                ?? throw new BusinessRuleViolationException('Für das Alter dieses ehemaligen Familienmitglieds ist kein passender Beitragssatz (Einzelperson) hinterlegt.');
        }

        throw new BusinessRuleViolationException('Für das Alter dieses Kindes ist kein passender Beitragssatz hinterlegt.');
    }

    /**
     * Ob eine Person älter ist als die höchste konfigurierte Altersgrenze der beiden
     * Kinder-Beitragssätze — und damit laut Beitragsordnung aus der Familien-Kinderpreisung
     * „herausgewachsen" ist. Ist keine der beiden Grenzen gesetzt (unbeschränkt oder Satz
     * gelöscht), lässt sich kein Herauswachsen feststellen.
     */
    private function hasAgedOutOfChildPricing(int $age, ?int $childExemptMaxAge, ?int $childPayingMaxAge): bool
    {
        $ceilings = array_values(array_filter(
            [$childExemptMaxAge, $childPayingMaxAge],
            static fn (?int $maxAge): bool => $maxAge !== null,
        ));
        if ($ceilings === []) {
            return false;
        }

        return $age > max($ceilings);
    }

    private function matchIndividualCategory(int $age): ?ContributionCategory
    {
        foreach ([ContributionCategory::IndividualJunior, ContributionCategory::IndividualSenior] as $category) {
            $rate = $this->rates->findByCategory($category);
            if ($rate !== null && $rate->appliesToAge($age)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Der Familienrabatt für Elternteile setzt ein eigenes, noch nicht aus der Kinderpreisung
     * „herausgewachsenes" Kind voraus — dieselbe Altersgrenze, ab der ein Kind selbst automatisch
     * zur Einzelmitgliedschaft wird (siehe `hasAgedOutOfChildPricing()`). Ein Kind, das laut
     * Beitragsordnung bereits als Einzelmitglied gilt, begründet keinen Familienrabatt mehr.
     *
     * @param list<Member> $householdMembers
     */
    private function hasQualifyingChild(array $householdMembers, \DateTimeImmutable $at): bool
    {
        $childExemptMaxAge = $this->rates->findByCategory(ContributionCategory::FamilyChildExempt)?->maxAge;
        $childPayingMaxAge = $this->rates->findByCategory(ContributionCategory::FamilyChildPaying)?->maxAge;

        foreach ($householdMembers as $member) {
            if ($member->familyRole === FamilyRole::Child
                && !$this->hasAgedOutOfChildPricing($member->age($at), $childExemptMaxAge, $childPayingMaxAge)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Geburtsreihenfolge des Kandidaten unter allen Kindern desselben Haushalts (1-basiert).
     *
     * @param list<Member> $householdMembers
     */
    private function childOrdinal(Member $candidate, array $householdMembers): int
    {
        $children = array_filter(
            [...$householdMembers, $candidate],
            static fn (Member $member): bool => $member->familyRole === FamilyRole::Child,
        );
        usort($children, static fn (Member $left, Member $right): int => $left->birthDate <=> $right->birthDate);

        foreach ($children as $index => $child) {
            if ($child->id === $candidate->id) {
                return $index + 1;
            }
        }

        return 1;
    }
}
