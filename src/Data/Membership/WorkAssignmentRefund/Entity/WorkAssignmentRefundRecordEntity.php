<?php

namespace App\Data\Membership\WorkAssignmentRefund\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Gutschrift-Historie (siehe `WorkAssignmentRefundRecord`) — wie die Lastschrift-Historie ohne Fremdschlüssel auf `member`. */
#[ORM\Entity]
#[ORM\Table(name: 'work_assignment_refund_record')]
#[ORM\Index(name: 'idx_work_assignment_refund_record_year', columns: ['refund_year', 'primary_member_number'])]
class WorkAssignmentRefundRecordEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(name: 'primary_member_number', type: Types::STRING, length: 20)]
        private string $primaryMemberNumber,
        #[ORM\Column(name: 'payer_member_id', type: Types::STRING, length: 36)]
        private string $payerMemberId,
        #[ORM\Column(name: 'refund_year', type: Types::INTEGER)]
        private int $year,
        #[ORM\Column(name: 'amount_cents', type: Types::INTEGER)]
        private int $amountCents,
        #[ORM\Column(name: 'message_id', type: Types::STRING, length: 35)]
        private string $messageId,
        #[ORM\Column(name: 'exported_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $exportedAt,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getPrimaryMemberNumber(): string { return $this->primaryMemberNumber; }
    public function getPayerMemberId(): string { return $this->payerMemberId; }
    public function getYear(): int { return $this->year; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getMessageId(): string { return $this->messageId; }
    public function getExportedAt(): \DateTimeImmutable { return $this->exportedAt; }
}
