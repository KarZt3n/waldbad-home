<?php

namespace App\Data\Membership\Cancellation\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'member_cancellation')]
#[ORM\Index(name: 'idx_member_cancellation_status', columns: ['status'])]
#[ORM\Index(name: 'idx_member_cancellation_member', columns: ['member_id'])]
class MemberCancellationEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(name: 'member_id', type: Types::STRING, length: 36)]
        private string $memberId,
        #[ORM\Column(name: 'member_number', type: Types::STRING, length: 20)]
        private string $memberNumber,
        #[ORM\Column(name: 'member_name', type: Types::STRING, length: 240)]
        private string $memberName,
        #[ORM\Column(name: 'primary_member_number', type: Types::STRING, length: 20)]
        private string $primaryMemberNumber,
        #[ORM\Column(name: 'requested_by_email', type: Types::STRING, length: 180)]
        private string $requestedByEmail,
        #[ORM\Column(name: 'requested_by_name', type: Types::STRING, length: 240, nullable: true)]
        private ?string $requestedByName,
        #[ORM\Column(name: 'requested_by_member_number', type: Types::STRING, length: 20, nullable: true)]
        private ?string $requestedByMemberNumber,
        #[ORM\Column(name: 'submitted_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $submittedAt,
        #[ORM\Column(name: 'exit_date', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $exitDate,
        #[ORM\Column(name: 'successor_member_id', type: Types::STRING, length: 36, nullable: true)]
        private ?string $successorMemberId,
        #[ORM\Column(name: 'successor_name', type: Types::STRING, length: 240, nullable: true)]
        private ?string $successorName,
        #[ORM\Column(name: 'contact_recipient_member_id', type: Types::STRING, length: 36, nullable: true)]
        private ?string $contactRecipientMemberId,
        #[ORM\Column(name: 'contact_recipient_name', type: Types::STRING, length: 240, nullable: true)]
        private ?string $contactRecipientName,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $status,
        #[ORM\Column(name: 'decided_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $decidedAt,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getMemberId(): string { return $this->memberId; }
    public function getMemberNumber(): string { return $this->memberNumber; }
    public function getMemberName(): string { return $this->memberName; }
    public function getPrimaryMemberNumber(): string { return $this->primaryMemberNumber; }
    public function getRequestedByEmail(): string { return $this->requestedByEmail; }
    public function getRequestedByName(): ?string { return $this->requestedByName; }
    public function getRequestedByMemberNumber(): ?string { return $this->requestedByMemberNumber; }
    public function getSubmittedAt(): \DateTimeImmutable { return $this->submittedAt; }
    public function getExitDate(): \DateTimeImmutable { return $this->exitDate; }
    public function getSuccessorMemberId(): ?string { return $this->successorMemberId; }
    public function getSuccessorName(): ?string { return $this->successorName; }
    public function getContactRecipientMemberId(): ?string { return $this->contactRecipientMemberId; }
    public function getContactRecipientName(): ?string { return $this->contactRecipientName; }
    public function getStatus(): string { return $this->status; }
    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }

    public function decide(string $status, ?\DateTimeImmutable $decidedAt): void
    {
        $this->status = $status;
        $this->decidedAt = $decidedAt;
    }
}
