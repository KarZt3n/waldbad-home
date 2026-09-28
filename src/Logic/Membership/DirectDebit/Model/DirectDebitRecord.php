<?php

namespace App\Logic\Membership\DirectDebit\Model;

/**
 * Ein erfolgter Lastschrift-Export für einen Zahler („Lastschrift-Historie“). Grundlage dafür,
 * ob eine Erstlastschrift fällig ist, ob der Beitrag des Eintrittsjahres schon eingezogen wurde
 * und wann zuletzt abgebucht wurde (siehe `PayerDirectDebitPlanner`).
 */
readonly class DirectDebitRecord
{
    public function __construct(
        public string $id,
        public string $payerMemberId,
        public string $mandateReference,
        public int $contributionYear,
        public SequenceType $sequenceType,
        public \DateTimeImmutable $collectionDate,
        public int $amountCents,
        public string $messageId,
        public \DateTimeImmutable $exportedAt,
    ) {
    }
}
