<?php

namespace App\Logic\Guestbook\Entry;

use App\Logic\Guestbook\Entry\Model\GuestbookEntry;

interface GuestbookEntryProcessorInterface
{
    public function save(GuestbookEntry $entry): GuestbookEntry;

    public function import(GuestbookEntry $entry): bool;
}
