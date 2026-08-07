<?php

namespace App\Logic\Guestbook\Entry\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class GuestbookEntry
{
    public function __construct(
        public string $id,
        public string $displayName,
        public ?string $email,
        public string $message,
        public GuestbookStatus $status,
        public \DateTimeImmutable $submittedAt,
        public ?\DateTimeImmutable $moderatedAt,
        public ?string $moderatedBy,
    ) {
        if (trim($this->displayName) === '' || trim($this->message) === '') {
            throw new BusinessRuleViolationException('Name und Nachricht sind erforderlich.');
        }
        if ($this->email !== null && filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessRuleViolationException('Die E-Mail-Adresse ist ungültig.');
        }
    }

    public function moderate(GuestbookStatus $status, string $moderatorId, \DateTimeImmutable $moderatedAt): self
    {
        if ($status === GuestbookStatus::Pending) {
            throw new BusinessRuleViolationException('Eine Moderationsentscheidung darf nicht "pending" sein.');
        }

        return new self(
            id: $this->id,
            displayName: $this->displayName,
            email: $this->email,
            message: $this->message,
            status: $status,
            submittedAt: $this->submittedAt,
            moderatedAt: $moderatedAt,
            moderatedBy: $moderatorId,
        );
    }
}
