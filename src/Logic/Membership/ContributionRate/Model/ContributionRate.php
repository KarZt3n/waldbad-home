<?php

namespace App\Logic\Membership\ContributionRate\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\PaymentInterval;

/**
 * Jeder Beitragssatz kann eine Altersspanne (`$minAge`/`$maxAge`, beide einschließlich, jeweils
 * optional) sowie einen Personenkreis (`$personGroup`: Einzelperson/Familie, optional) hinterlegen.
 * Die automatische Beitragsermittlung (`MemberContributionCalculator`) ordnet ein Mitglied über die
 * Altersspanne statt über fest im Code verdrahtete Altersgrenzen einer Kategorie zu; der
 * Personenkreis wird u. a. genutzt, um bei Neuanlage eines Mitglieds passende einmalige Gebühren
 * (Zeitraum `Once`, z. B. eine Beitrittsgebühr) zu finden — Bezeichnung, Betrag, Zeitraum,
 * Altersspanne und Personenkreis sind für jeden Beitragssatz im Admin anpassbar.
 *
 * Kategorien mit fester Bedeutung (`$category !== null`) sind die von der Berechnung erwarteten
 * sechs Grundkategorien (siehe `ContributionCategory`); sie sind wie jeder andere Beitragssatz
 * löschbar. Fehlt eine Kategorie zur Berechnungszeit, meldet der Rechner dies als fachlichen
 * Fehler, statt eine falsche Annahme zu treffen — sie kann jederzeit über „Neuer Beitragssatz“
 * mit derselben Kategorie neu angelegt werden. Zusätzliche, frei angelegte Beitragssätze
 * (`$category === null`) dienen nur der Übersicht/Dokumentation oder — mit Zeitraum `Once` und
 * gesetztem Personenkreis — als automatisch bei Neuanlage berechnete einmalige Gebühr.
 */
readonly class ContributionRate
{
    public function __construct(
        public string $id,
        public ?ContributionCategory $category,
        public string $label,
        public int $amountCents,
        public PaymentInterval $period,
        public ?PersonGroup $personGroup = null,
        public ?int $minAge = null,
        public ?int $maxAge = null,
    ) {
        if (trim($this->label) === '') {
            throw new BusinessRuleViolationException('Die Bezeichnung des Beitragssatzes ist erforderlich.');
        }
        if ($this->amountCents < 0) {
            throw new BusinessRuleViolationException('Der Betrag eines Beitragssatzes darf nicht negativ sein.');
        }
        if ($this->minAge !== null && $this->minAge < 0) {
            throw new BusinessRuleViolationException('Die untere Altersgrenze darf nicht negativ sein.');
        }
        if ($this->maxAge !== null && $this->maxAge < 0) {
            throw new BusinessRuleViolationException('Die obere Altersgrenze darf nicht negativ sein.');
        }
        if ($this->minAge !== null && $this->maxAge !== null && $this->maxAge < $this->minAge) {
            throw new BusinessRuleViolationException('Die Altersspanne ist ungültig: "bis" darf nicht vor "von" liegen.');
        }
        if ($this->period === PaymentInterval::Once && $this->personGroup === null) {
            throw new BusinessRuleViolationException('Für eine einmalige Gebühr muss der Personenkreis (Einzelperson/Familie) angegeben werden.');
        }
    }

    public function annualAmountCents(): int
    {
        return $this->amountCents * $this->period->occurrencesPerYear();
    }

    /**
     * Ob dieser Beitragssatz für ein Mitglied dieses Alters infrage kommt. Eine nicht gesetzte
     * Altersgrenze gilt als unbeschränkt (z. B. beide `null` = gilt für jedes Alter).
     */
    public function appliesToAge(int $age): bool
    {
        if ($this->minAge !== null && $age < $this->minAge) {
            return false;
        }

        return $this->maxAge === null || $age <= $this->maxAge;
    }

    public function withUpdatedRate(
        string $label,
        int $amountCents,
        PaymentInterval $period,
        ?PersonGroup $personGroup,
        ?int $minAge,
        ?int $maxAge,
    ): self {
        return new self($this->id, $this->category, $label, $amountCents, $period, $personGroup, $minAge, $maxAge);
    }
}
