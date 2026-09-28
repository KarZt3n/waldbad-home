<?php

namespace App\Logic\Membership\DirectDebit\Model;

use App\Logic\Membership\Member\Model\Member;

/**
 * Alles, was für die Lastschrift eines Zahlers ermittelt wurde, bevor die Redaktion Positionen,
 * Fälligkeitsdatum und Verwendungszweck festlegt (siehe `PayerDirectDebitPlanner`). `$blockers`
 * verhindern den Export, `$warnings` sind nur Hinweise.
 *
 * `$joiningYearDebit`: Lastschrift für das Eintrittsjahr eines im laufenden Jahr eingetretenen
 * Zahlers, für das noch nichts eingezogen wurde — sie wird sofort fällig und rückt die „Nächste
 * Buchung“ nicht vor. `$contributionYear` ist das Beitragsjahr, für das abgebucht wird.
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
        public SequenceType $defaultSequenceType,
        public int $contributionYear,
        public bool $joiningYearDebit,
        public ?DirectDebitRecord $lastRecord,
    ) {
    }
}
