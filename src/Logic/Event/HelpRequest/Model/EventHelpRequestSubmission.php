<?php

namespace App\Logic\Event\HelpRequest\Model;

/** Eine eingegangene Helferanmeldung samt mitgebrachter Personen, wie sie dem Verein gemeldet wird. */
readonly class EventHelpRequestSubmission
{
    /**
     * @param list<string> $companionNames mitgebrachte Personen („Vorname Nachname“)
     * @param list<string> $activityNames  gewählte Aktivitäten
     * @param ?string      $memberNumber   Mitgliedsnummer der anmeldenden Person, falls automatisch zugeordnet
     */
    public function __construct(
        public VolunteerEvent $event,
        public string $firstName,
        public string $lastName,
        public array $companionNames,
        public array $activityNames,
        public string $message,
        public ?string $email,
        public ?string $memberNumber,
    ) {
    }

    /** @return non-empty-list<string> */
    public function personNames(): array
    {
        return [$this->firstName.' '.$this->lastName, ...$this->companionNames];
    }
}
