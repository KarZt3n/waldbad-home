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
}
