<?php

namespace App\Logic\Guestbook\Entry\UseCase;

use App\Logic\Guestbook\Entry\Manager\GuestbookManagerInterface;
use App\Logic\Guestbook\Entry\Model\GuestbookEntry;
use App\Logic\Guestbook\Entry\Model\GuestbookStatus;

readonly class ImportPublishedGuestbookEntryUseCase
{
    public function __construct(private GuestbookManagerInterface $manager)
    {
    }

    public function execute(string $displayName, string $message, \DateTimeImmutable $submittedAt): bool
    {
        $entry = new GuestbookEntry(
            id: $this->createId($displayName, $message, $submittedAt),
            displayName: $displayName,
            email: null,
            message: $message,
            status: GuestbookStatus::Published,
            submittedAt: $submittedAt,
            moderatedAt: null,
            moderatedBy: null,
        );

        return $this->manager->import($entry);
    }

    private function createId(string $displayName, string $message, \DateTimeImmutable $submittedAt): string
    {
        $hex = substr(hash('sha256', implode("\0", [$displayName, $submittedAt->format(DATE_ATOM), $message])), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
