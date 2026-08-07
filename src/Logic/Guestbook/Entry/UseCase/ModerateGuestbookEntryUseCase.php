<?php

namespace App\Logic\Guestbook\Entry\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Guestbook\Entry\Dto\GuestbookEntryResponse;
use App\Logic\Guestbook\Entry\Manager\GuestbookManagerInterface;
use App\Logic\Guestbook\Entry\Model\GuestbookStatus;

readonly class ModerateGuestbookEntryUseCase
{
    public function __construct(
        private GuestbookManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id, string $action, string $moderatorId): GuestbookEntryResponse
    {
        $status = match ($action) {
            'approve' => GuestbookStatus::Published,
            'reject' => GuestbookStatus::Rejected,
            'mark-spam' => GuestbookStatus::Spam,
            default => throw new BusinessRuleViolationException('Unbekannte Moderationsaktion.'),
        };

        $entry = $this->manager->get($id)->moderate($status, $moderatorId, $this->clock->now());

        return GuestbookEntryResponse::fromEntry($this->manager->save($entry), true);
    }
}
