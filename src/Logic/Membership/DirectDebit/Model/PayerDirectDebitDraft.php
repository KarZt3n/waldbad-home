<?php

namespace App\Logic\Membership\DirectDebit\Model;

use App\Logic\Membership\Member\Model\Member;

/**
 * Alles, was für die Lastschrift eines Zahlers ermittelt wurde, bevor die Redaktion Positionen,
 * Fälligkeitsdatum und Verwendungszweck festlegt (siehe `PayerDirectDebitPlanner`). `$blockers`
 * verhindern den Export, `$warnings` sind nur Hinweise.
 */
readonly class PayerDirectDebitDraft
{
    /**
     * @param list<DirectDebitPosition> $positions
     * @param list<string>               $blockers
     * @param list<string>               $warnings
     */
    public function __construct(
        public Member $payer,
        public string $debtorName,
        public \DateTimeImmutable $mandateSignedOn,
        public DirectDebitCreditor $creditor,
        public array $positions,
        public array $blockers,
        public array $warnings,
        public \DateTimeImmutable $defaultCollectionDate,
        public string $defaultRemittanceInformation,
    ) {
    }
}
