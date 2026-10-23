<?php

namespace App\Logic\Guestbook\Entry;

/** Absoluter Link in die Redaktion auf einen Gästebucheintrag (für Benachrichtigungsmails). */
interface GuestbookEntryAdminLinkBuilderInterface
{
    public function build(string $entryId): string;
}
