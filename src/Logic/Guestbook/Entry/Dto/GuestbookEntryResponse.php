<?php

namespace App\Logic\Guestbook\Entry\Dto;

use App\Logic\Guestbook\Entry\Model\GuestbookEntry;
use App\Logic\Guestbook\Entry\Model\GuestbookStatus;

readonly class GuestbookEntryResponse
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
    }

    public static function fromEntry(GuestbookEntry $entry, bool $includePrivateData): self
    {
        return new self(
            id: $entry->id,
            displayName: $entry->displayName,
            email: $includePrivateData ? $entry->email : null,
            message: $entry->message,
            status: $entry->status,
            submittedAt: $entry->submittedAt,
            moderatedAt: $includePrivateData ? $entry->moderatedAt : null,
            moderatedBy: $includePrivateData ? $entry->moderatedBy : null,
        );
    }
}
