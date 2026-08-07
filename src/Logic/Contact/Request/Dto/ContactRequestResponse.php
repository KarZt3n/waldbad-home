<?php

namespace App\Logic\Contact\Request\Dto;

use App\Logic\Contact\Request\Model\ContactRequest;
use App\Logic\Contact\Request\Model\ContactRequestStatus;

readonly class ContactRequestResponse
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
    }

    public static function fromRequest(ContactRequest $request): self
    {
        return new self(
            id: $request->id,
            name: $request->name,
            email: $request->email,
            subject: $request->subject,
            message: $request->message,
            status: $request->status,
            submittedAt: $request->submittedAt,
            updatedAt: $request->updatedAt,
        );
    }
}
