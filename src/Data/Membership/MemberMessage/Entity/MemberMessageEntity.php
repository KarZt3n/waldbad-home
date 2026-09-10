<?php

namespace App\Data\Membership\MemberMessage\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'member_message')]
#[ORM\Index(name: 'idx_member_message_status_submitted', columns: ['status', 'submitted_at'])]
class MemberMessageEntity
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
        #[ORM\Column(type: Types::TEXT)]
        private string $message,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $status,
        #[ORM\Column(name: 'submitted_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $submittedAt,
        #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getMemberId(): string { return $this->memberId; }
    public function getMemberNumber(): string { return $this->memberNumber; }
    public function getMemberName(): string { return $this->memberName; }
    public function getMessage(): string { return $this->message; }
    public function getStatus(): string { return $this->status; }
    public function getSubmittedAt(): \DateTimeImmutable { return $this->submittedAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function changeStatus(string $status, \DateTimeImmutable $updatedAt): void
    {
        $this->status = $status;
        $this->updatedAt = $updatedAt;
    }
}
