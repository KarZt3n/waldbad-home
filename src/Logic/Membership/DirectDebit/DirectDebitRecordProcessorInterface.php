<?php

namespace App\Logic\Membership\DirectDebit;

use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;

interface DirectDebitRecordProcessorInterface
{
    public function save(DirectDebitRecord $record): DirectDebitRecord;

    /** Schreibt die Historie eines Zahlers auf ein anderes Mitglied um (Zahler-Übertragung, siehe `PayerTransfer`). */
    public function reassignPayer(string $fromPayerMemberId, string $toPayerMemberId): void;
}
