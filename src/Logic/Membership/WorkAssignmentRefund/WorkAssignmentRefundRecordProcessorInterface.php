<?php

namespace App\Logic\Membership\WorkAssignmentRefund;

use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;

interface WorkAssignmentRefundRecordProcessorInterface
{
    public function save(WorkAssignmentRefundRecord $record): WorkAssignmentRefundRecord;

    /** Schreibt die Historie eines Zahlers auf ein anderes Mitglied um (Zahler-Übertragung, siehe `PayerTransfer`). */
    public function reassignPayer(string $fromPayerMemberId, string $toPayerMemberId): void;

    /** Schreibt alle Einträge eines Haushalts auf eine neue Hauptnummer um (Zahler-Übertragung, siehe `PayerTransfer`). */
    public function reassignHousehold(string $fromPrimaryMemberNumber, string $toPrimaryMemberNumber): void;
}
