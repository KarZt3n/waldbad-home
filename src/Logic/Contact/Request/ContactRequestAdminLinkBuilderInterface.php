<?php

namespace App\Logic\Contact\Request;

/** Absoluter Link in die Redaktion auf eine Kontaktanfrage (für Benachrichtigungsmails). */
interface ContactRequestAdminLinkBuilderInterface
{
    public function build(string $contactRequestId): string;
}
