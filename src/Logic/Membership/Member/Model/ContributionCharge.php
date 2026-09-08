<?php

namespace App\Logic\Membership\Member\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Eine tatsächlich berechnete Gebühr aus einem Beitragssatz mit Zeitraum „Einmalig“
 * (z. B. die Beitrittsgebühr), festgehalten mit Zeitstempel. Wird nur bei Neuanlage eines
 * Mitglieds vergeben (siehe `MemberOnboardingOrchestrator`) und im Nachhinein nicht mehr verändert.
 */
readonly class ContributionCharge
{
    public function __construct(
        public string $id,
        public string $label,
        public int $amountCents,
        public \DateTimeImmutable $chargedAt,
    ) {
        if (trim($this->label) === '') {
            throw new BusinessRuleViolationException('Die Bezeichnung einer berechneten Gebühr darf nicht leer sein.');
        }
        if ($this->amountCents < 0) {
            throw new BusinessRuleViolationException('Der Betrag einer berechneten Gebühr darf nicht negativ sein.');
        }
    }
}
