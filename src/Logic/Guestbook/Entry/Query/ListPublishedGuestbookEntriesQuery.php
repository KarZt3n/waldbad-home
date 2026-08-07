<?php

namespace App\Logic\Guestbook\Entry\Query;

use App\Logic\Guestbook\Entry\Dto\GuestbookEntryResponse;
use App\Logic\Guestbook\Entry\Manager\GuestbookManagerInterface;
use App\Logic\Guestbook\Entry\Model\GuestbookEntry;

readonly class ListPublishedGuestbookEntriesQuery
{
    public function __construct(private GuestbookManagerInterface $manager)
    {
    }

    /**
     * @return list<GuestbookEntryResponse>
     */
    public function execute(int $limit, int $offset): array
    {
        return array_map(
            static fn (GuestbookEntry $entry): GuestbookEntryResponse => GuestbookEntryResponse::fromEntry($entry, false),
            $this->manager->published($limit, $offset),
        );
    }

    public function count(): int
    {
        return $this->manager->countPublished();
    }
}
