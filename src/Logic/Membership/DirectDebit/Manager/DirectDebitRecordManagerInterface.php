<?php

namespace App\Logic\Membership\DirectDebit\Manager;

use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;

interface DirectDebitRecordManagerInterface
{
    /** @return list<DirectDebitRecord> neueste zuerst */
    public function findByPayerMemberId(string $payerMemberId): array;

    /** @return list<DirectDebitRecord> neueste zuerst */
    public function findAll(): array;

    public function save(DirectDebitRecord $record): DirectDebitRecord;

    /** Schreibt die Historie eines Zahlers auf ein anderes Mitglied um (Zahler-Übertragung, siehe `PayerTransfer`). */
    public function reassignPayer(string $fromPayerMemberId, string $toPayerMemberId): void;
}
