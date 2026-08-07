<?php

namespace App\Data\Contact\Request\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'contact_request')]
#[ORM\Index(name: 'idx_contact_status_submitted', columns: ['status', 'submitted_at'])]
class ContactRequestEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $name,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $email,
        #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
        private ?string $subject,
        #[ORM\Column(type: Types::TEXT)]
        private string $message,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $status,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $submittedAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }
    public function getSubject(): ?string { return $this->subject; }
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
