<?php

namespace App\Logic\Membership\ContributionRate\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\PaymentInterval;

/**
 * Eine geplante, künftige Version eines kompletten Beitragssatzes (nicht nur ein neuer Betrag) —
 * z. B. „ab 01.01.2027: Arbeitseinsatz-Pauschale 20,00 €, Altersspanne ab 10 Jahre“. Trägt dieselben
 * Regeln wie `ContributionRate` selbst (unabhängig geprüft, damit ein Eingabefehler sofort beim
 * Speichern der geplanten Änderung auffällt statt erst Monate später beim automatischen Anwenden,
 * siehe `ContributionRate::applyPendingChange()`).
 */
readonly class PendingContributionRateChange
{
    public function __construct(
        public string $label,
        public int $amountCents,
        public PaymentInterval $period,
        public ?PersonGroup $personGroup,
        public ?int $minAge,
        public ?int $maxAge,
        public \DateTimeImmutable $validFrom,
    ) {
        if (trim($this->label) === '') {
            throw new BusinessRuleViolationException('Die geplante Bezeichnung ist erforderlich.');
        }
        if ($this->amountCents < 0) {
            throw new BusinessRuleViolationException('Der geplante Betrag darf nicht negativ sein.');
        }
        if ($this->minAge !== null && $this->minAge < 0) {
            throw new BusinessRuleViolationException('Die geplante untere Altersgrenze darf nicht negativ sein.');
        }
        if ($this->maxAge !== null && $this->maxAge < 0) {
            throw new BusinessRuleViolationException('Die geplante obere Altersgrenze darf nicht negativ sein.');
        }
        if ($this->minAge !== null && $this->maxAge !== null && $this->maxAge < $this->minAge) {
            throw new BusinessRuleViolationException('Die geplante Altersspanne ist ungültig: "bis" darf nicht vor "von" liegen.');
        }
        if ($this->period === PaymentInterval::Once && $this->personGroup === null) {
            throw new BusinessRuleViolationException('Für eine geplante einmalige Gebühr muss der Personenkreis angegeben werden.');
        }
    }
}
