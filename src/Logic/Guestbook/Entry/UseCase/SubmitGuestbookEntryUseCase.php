<?php

namespace App\Logic\Guestbook\Entry\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Guestbook\Entry\Dto\GuestbookEntryResponse;
use App\Logic\Guestbook\Entry\GuestbookEntryNotifierInterface;
use App\Logic\Guestbook\Entry\Manager\GuestbookManagerInterface;
use App\Logic\Guestbook\Entry\Model\GuestbookEntry;
use App\Logic\Guestbook\Entry\Model\GuestbookStatus;

readonly class SubmitGuestbookEntryUseCase
{
    public function __construct(
        private GuestbookManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
        private GuestbookEntryNotifierInterface $notifier,
    ) {
    }

    public function execute(string $displayName, ?string $email, string $message): GuestbookEntryResponse
    {
        $entry = new GuestbookEntry(
            id: $this->identifierGenerator->generate(),
            displayName: trim($displayName),
            email: $email === null ? null : mb_strtolower(trim($email)),
            message: trim($message),
            status: GuestbookStatus::Pending,
            submittedAt: $this->clock->now(),
            moderatedAt: null,
            moderatedBy: null,
        );

        $saved = $this->manager->save($entry);
        $this->notifier->entrySubmitted($saved);

        return GuestbookEntryResponse::fromEntry($saved, false);
    }
}
