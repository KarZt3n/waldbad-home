<?php

namespace App\Logic\Event\HelpRequest;

use App\Logic\Event\HelpRequest\Model\EventHelpRequestSubmission;

/**
 * Adapter zum Mailversand der Einstellungen: meldet eine neue Helferanmeldung („Ich möchte helfen!“)
 * an die dafür konfigurierten Empfänger, ohne dass die Veranstaltungen Mailvorlagen oder
 * Empfängerlisten kennen.
 */
interface EventHelpRequestNotifierInterface
{
    /** Eine Anmeldung samt mitgebrachter Personen als eine Benachrichtigung. */
    public function helpRequestSubmitted(EventHelpRequestSubmission $submission): void;
}
