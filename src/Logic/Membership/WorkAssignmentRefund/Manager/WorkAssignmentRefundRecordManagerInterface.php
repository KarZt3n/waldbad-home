<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Manager;

use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;

interface WorkAssignmentRefundRecordManagerInterface
{
    /** @return list<WorkAssignmentRefundRecord> neueste zuerst */
    public function findByYear(int $year): array;

    /**
     * Legt die Einträge eines Exports gemeinsam an (ein Speichervorgang statt einer je Empfänger).
     *
     * @param list<WorkAssignmentRefundRecord> $records
     */
    public function saveAll(array $records): void;

    /** Schreibt die Historie eines Zahlers auf ein anderes Mitglied um (Zahler-Übertragung, siehe `PayerTransfer`). */
    public function reassignPayer(string $fromPayerMemberId, string $toPayerMemberId): void;

    /** Schreibt alle Einträge eines Haushalts auf eine neue Hauptnummer um (Zahler-Übertragung, siehe `PayerTransfer`). */
    public function reassignHousehold(string $fromPrimaryMemberNumber, string $toPrimaryMemberNumber): void;
}
