<?php

namespace App\Logic\Event\HelpRequest;

/** Absoluter Link in die Redaktion auf die Helferanmeldungen einer Veranstaltung (für Benachrichtigungsmails). */
interface EventHelpRequestAdminLinkBuilderInterface
{
    public function build(string $eventIdentifier): string;
}
