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
 * Altersspanne, Personenkreis und geplante Änderung sind für jeden Beitragssatz im Admin anpassbar.
 *
 * Kategorien mit fester Bedeutung (`$category !== null`) sind die von der Berechnung erwarteten
 * sechs Grundkategorien (siehe `ContributionCategory`); sie sind wie jeder andere Beitragssatz
 * löschbar. Fehlt eine Kategorie zur Berechnungszeit, meldet der Rechner dies als fachlichen
 * Fehler, statt eine falsche Annahme zu treffen — sie kann jederzeit über „Neuer Beitragssatz“
 * mit derselben Kategorie neu angelegt werden. Zusätzliche, frei angelegte Beitragssätze
 * (`$category === null`) dienen nur der Übersicht/Dokumentation oder — mit Zeitraum `Once` und
 * gesetztem Personenkreis — als automatisch bei Neuanlage berechnete einmalige Gebühr.
 *
 * Das „gültig ab"-Datum der Beitragsordnung ist bewusst **nicht** hier verankert, sondern
 * projektweit einheitlich in `ContributionRateSettings::$validFrom` — es gilt für alle
 * Beitragssätze gemeinsam, nicht je Satz. Stattdessen kann hier eine **geplante** künftige Version
 * des **gesamten** Beitragssatzes hinterlegt werden (`$pending`, z. B. eine gleichzeitige Erhöhung
 * der Arbeitseinsatz-Pauschale UND der unteren Altersgrenze zum selben Stichtag) — nicht nur der
 * Betrag: eine Änderung betrifft oft mehrere Felder gemeinsam. Sobald `$pending->validFrom` erreicht
 * ist, wird sie automatisch aktiv (`applyPendingChange()`, ausgelöst durch `ContributionRateManager`
 * beim nächsten Lesezugriff — dieses Projekt hat keine Scheduler-Infrastruktur für einen exakten
 * Cronjob) und das Datum wird zugleich zum neuen `ContributionRateSettings::$validFrom` für alle
 * Beitragssätze.
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
        public ?PendingContributionRateChange $pending = null,
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

    public function hasPendingChange(): bool
    {
        return $this->pending !== null;
    }

    /**
     * Ob die geplante Änderung heute schon gilt — nur der Kalendertag zählt, nicht die Uhrzeit
     * (siehe `ContributionRateManager`).
     */
    public function isPendingChangeDue(\DateTimeImmutable $at): bool
    {
        return $this->pending !== null && $this->pending->validFrom <= $at;
    }

    /**
     * Übernimmt die geplante Version als aktuellen Beitragssatz (alle Felder, nicht nur den Betrag)
     * und löscht die geplante Änderung — wird nicht direkt vom Admin ausgelöst, sondern automatisch
     * von `ContributionRateManager`, sobald `isPendingChangeDue()` zutrifft.
     */
    public function applyPendingChange(): self
    {
        if ($this->pending === null) {
            return $this;
        }

        return new self(
            $this->id,
            $this->category,
            $this->pending->label,
            $this->pending->amountCents,
            $this->pending->period,
            $this->pending->personGroup,
            $this->pending->minAge,
            $this->pending->maxAge,
            pending: null,
        );
    }

    public function withUpdatedRate(
        string $label,
        int $amountCents,
        PaymentInterval $period,
        ?PersonGroup $personGroup,
        ?int $minAge,
        ?int $maxAge,
        ?PendingContributionRateChange $pending,
    ): self {
        return new self($this->id, $this->category, $label, $amountCents, $period, $personGroup, $minAge, $maxAge, $pending);
    }
}
