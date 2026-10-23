<?php

namespace App\Logic\Guestbook\Entry;

use App\Logic\Guestbook\Entry\Model\GuestbookEntry;

/**
 * Adapter zum Mailversand der Einstellungen: meldet einen neuen, noch freizugebenden
 * Gästebucheintrag an die dafür konfigurierten Empfänger, ohne dass das Gästebuch Mailvorlagen
 * oder Empfängerlisten kennt.
 */
interface GuestbookEntryNotifierInterface
{
    public function entrySubmitted(GuestbookEntry $entry): void;
}
