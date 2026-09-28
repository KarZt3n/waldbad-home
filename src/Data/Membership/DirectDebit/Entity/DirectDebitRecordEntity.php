<?php

namespace App\Data\Membership\DirectDebit\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Lastschrift-Historie (siehe `DirectDebitRecord`) — bewusst ohne Fremdschlüssel auf `member`, damit sie das Löschen eines Mitglieds überdauert. */
#[ORM\Entity]
#[ORM\Table(name: 'direct_debit_record')]
#[ORM\Index(name: 'idx_direct_debit_record_payer', columns: ['payer_member_id', 'exported_at'])]
class DirectDebitRecordEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(name: 'payer_member_id', type: Types::STRING, length: 36)]
        private string $payerMemberId,
        #[ORM\Column(name: 'mandate_reference', type: Types::STRING, length: 60)]
        private string $mandateReference,
        #[ORM\Column(name: 'contribution_year', type: Types::INTEGER)]
        private int $contributionYear,
        #[ORM\Column(name: 'sequence_type', type: Types::STRING, length: 4)]
        private string $sequenceType,
        #[ORM\Column(name: 'collection_date', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $collectionDate,
        #[ORM\Column(name: 'amount_cents', type: Types::INTEGER)]
        private int $amountCents,
        #[ORM\Column(name: 'message_id', type: Types::STRING, length: 35)]
        private string $messageId,
        #[ORM\Column(name: 'exported_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $exportedAt,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getPayerMemberId(): string { return $this->payerMemberId; }
    public function getMandateReference(): string { return $this->mandateReference; }
    public function getContributionYear(): int { return $this->contributionYear; }
    public function getSequenceType(): string { return $this->sequenceType; }
    public function getCollectionDate(): \DateTimeImmutable { return $this->collectionDate; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getMessageId(): string { return $this->messageId; }
    public function getExportedAt(): \DateTimeImmutable { return $this->exportedAt; }
}
