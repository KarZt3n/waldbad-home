<?php

namespace App\Logic\Event\HelpRequest\Dto;

readonly class EventHelpRequestBroadcastRecipientsResponse
{
    /**
     * @param list<string> $defaultEmails Wird beim Absenden ohne weiteres Zutun verwendet (siehe
     *                                     `EventHelpRequestRecipientResolver`).
     * @param list<string> $suggestedEmails Alle plausiblen Alternativen zum Nachtragen im „An:"-Feld
     *                                      (u. a. Haushaltsmitglieder, deren Adresse nicht automatisch
     *                                      verwendet würde) — enthält `defaultEmails` mit.
     */
    public function __construct(
        public array $defaultEmails,
        public array $suggestedEmails,
    ) {
    }
}
