<?php

namespace App\Logic\Guestbook\Entry\Query;

use App\Logic\Guestbook\Entry\Dto\GuestbookEntryResponse;
use App\Logic\Guestbook\Entry\Manager\GuestbookManagerInterface;
use App\Logic\Guestbook\Entry\Model\GuestbookEntry;

readonly class ListGuestbookEntriesQuery
{
    public function __construct(private GuestbookManagerInterface $manager)
    {
    }

    /**
     * @return list<GuestbookEntryResponse>
     */
    public function execute(): array
    {
        return array_map(
            static fn (GuestbookEntry $entry): GuestbookEntryResponse => GuestbookEntryResponse::fromEntry($entry, true),
            $this->manager->all(),
        );
    }
}
