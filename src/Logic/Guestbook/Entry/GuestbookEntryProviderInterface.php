<?php

namespace App\Logic\Guestbook\Entry;

use App\Logic\Guestbook\Entry\Model\GuestbookEntry;

interface GuestbookEntryProviderInterface
{
    public function find(string $id): ?GuestbookEntry;

    /**
     * @return list<GuestbookEntry>
     */
    public function findPublished(int $limit, int $offset): array;

    public function countPublished(): int;

    /**
     * @return list<GuestbookEntry>
     */
    public function findAll(): array;
}
