<?php

namespace App\Data\Guestbook\Entry\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'guestbook_entry')]
#[ORM\Index(name: 'idx_guestbook_status_submitted', columns: ['status', 'submitted_at'])]
class GuestbookEntryEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $displayName,
        #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
        private ?string $email,
        #[ORM\Column(type: Types::TEXT)]
        private string $message,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $status,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $submittedAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $moderatedAt,
        #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
        private ?string $moderatedBy,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getDisplayName(): string { return $this->displayName; }
    public function getEmail(): ?string { return $this->email; }
    public function getMessage(): string { return $this->message; }
    public function getStatus(): string { return $this->status; }
    public function getSubmittedAt(): \DateTimeImmutable { return $this->submittedAt; }
    public function getModeratedAt(): ?\DateTimeImmutable { return $this->moderatedAt; }
    public function getModeratedBy(): ?string { return $this->moderatedBy; }

    public function moderate(string $status, \DateTimeImmutable $moderatedAt, string $moderatedBy): void
    {
        $this->status = $status;
        $this->moderatedAt = $moderatedAt;
        $this->moderatedBy = $moderatedBy;
    }
}
