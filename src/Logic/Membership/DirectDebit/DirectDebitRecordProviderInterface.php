<?php

namespace App\Logic\Membership\DirectDebit;

use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;

interface DirectDebitRecordProviderInterface
{
    /** @return list<DirectDebitRecord> neueste zuerst */
    public function findByPayerMemberId(string $payerMemberId): array;

    /** @return list<DirectDebitRecord> neueste zuerst */
    public function findAll(): array;

    /** @return list<string> IDs aller bereits per Lastschrift eingezogenen Einmalgebühren */
    public function findCollectedChargeIds(): array;
}
