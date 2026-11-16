<?php

namespace App\Logic\Membership\DirectDebit\Model;

/**
 * Eine einzelne, in der Vorschau an- oder abwählbare Position einer Lastschrift (z. B. der Beitrag
 * eines Familienmitglieds oder eine Beitrittsgebühr). `$amountCents` ist der Betrag dieser einen
 * Abbuchung — bei unterjährigem Zahlintervall der entsprechende Anteil von `$annualAmountCents`.
 * `$chargeId` ist nur bei Einmalgebühren gesetzt und wird nach dem Export als eingezogen vermerkt.
 */
readonly class DirectDebitPosition
{
    public function __construct(
        public string $id,
        public string $memberId,
        public string $memberNumber,
        public string $memberName,
        public DirectDebitPositionKind $kind,
        public string $label,
        public int $amountCents,
        public ?int $annualAmountCents,
        public bool $selectedByDefault,
        public ?string $chargeId = null,
    ) {
    }
}
