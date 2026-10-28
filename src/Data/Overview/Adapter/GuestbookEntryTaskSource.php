<?php

namespace App\Data\Overview\Adapter;

use App\Logic\Guestbook\Entry\Manager\GuestbookManagerInterface;
use App\Logic\Guestbook\Entry\Model\GuestbookEntry;
use App\Logic\Guestbook\Entry\Model\GuestbookStatus;
use App\Logic\Overview\Model\OverviewTaskType;
use App\Logic\Overview\OverviewTaskSourceInterface;

readonly class GuestbookEntryTaskSource implements OverviewTaskSourceInterface
{
    public function __construct(private GuestbookManagerInterface $entries)
    {
    }

    public function type(): OverviewTaskType
    {
        return OverviewTaskType::GuestbookEntries;
    }

    public function openItemDates(): array
    {
        return array_values(array_map(
            static fn (GuestbookEntry $entry): \DateTimeImmutable => $entry->submittedAt,
            array_filter(
                $this->entries->all(),
                static fn (GuestbookEntry $entry): bool => $entry->status === GuestbookStatus::Pending,
            ),
        ));
    }
}
