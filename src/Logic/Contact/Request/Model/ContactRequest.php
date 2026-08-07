<?php

namespace App\Logic\Contact\Request\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class ContactRequest
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?string $subject,
        public string $message,
        public ContactRequestStatus $status,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
    ) {
        if (trim($this->name) === '' || trim($this->message) === '' || filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessRuleViolationException('Name, gültige E-Mail-Adresse und Nachricht sind erforderlich.');
        }
    }

    public function changeStatus(ContactRequestStatus $status, \DateTimeImmutable $updatedAt): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            subject: $this->subject,
            message: $this->message,
            status: $status,
            submittedAt: $this->submittedAt,
            updatedAt: $updatedAt,
        );
    }
}
