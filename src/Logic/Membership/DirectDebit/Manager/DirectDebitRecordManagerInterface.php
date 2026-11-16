<?php

namespace App\Logic\Membership\DirectDebit\Manager;

use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;

interface DirectDebitRecordManagerInterface
{
    /** @return list<DirectDebitRecord> neueste zuerst */
    public function findByPayerMemberId(string $payerMemberId): array;

    /** @return list<DirectDebitRecord> neueste zuerst */
    public function findAll(): array;

    /** @return list<string> IDs aller bereits per Lastschrift eingezogenen Einmalgebühren */
    public function findCollectedChargeIds(): array;

    public function save(DirectDebitRecord $record): DirectDebitRecord;

    /** Schreibt die Historie eines Zahlers auf ein anderes Mitglied um (Zahler-Übertragung, siehe `PayerTransfer`). */
    public function reassignPayer(string $fromPayerMemberId, string $toPayerMemberId): void;

    /**
     * Vermerkt Einmalgebühren als mit dem Export `$recordId` eingezogen.
     *
     * @param list<string> $chargeIds
     */
    public function markChargesCollected(array $chargeIds, string $recordId, \DateTimeImmutable $collectedAt): void;
}
