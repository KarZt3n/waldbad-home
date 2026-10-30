<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Model;

/**
 * Eine erfolgte Gutschrift-Überweisung für die geleisteten Arbeitsstunden eines Haushalts
 * („Gutschrift-Historie“). Grundlage dafür, dass dasselbe Beitragsjahr nicht doppelt erstattet
 * wird — kommen später Stunden hinzu, wird nur noch die Differenz überwiesen (siehe
 * `WorkAssignmentRefundPlanner`).
 */
readonly class WorkAssignmentRefundRecord
{
    public function __construct(
        public string $id,
        public string $primaryMemberNumber,
        public string $payerMemberId,
        public int $year,
        public int $amountCents,
        public string $messageId,
        public \DateTimeImmutable $exportedAt,
    ) {
    }
}
