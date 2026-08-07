<?php

namespace App\Logic\Guestbook\Entry\Manager;

use App\Logic\Guestbook\Entry\Model\GuestbookEntry;

interface GuestbookManagerInterface
{
    public function get(string $id): GuestbookEntry;

    /**
     * @return list<GuestbookEntry>
     */
    public function published(int $limit, int $offset): array;

    public function countPublished(): int;

    /**
     * @return list<GuestbookEntry>
     */
    public function all(): array;

    public function save(GuestbookEntry $entry): GuestbookEntry;

    public function import(GuestbookEntry $entry): bool;
}
