<?php

namespace App\Logic\Membership\DirectDebit;

use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;

interface DirectDebitRecordProcessorInterface
{
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
