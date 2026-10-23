<?php

namespace App\Logic\Event\HelpRequest\Model;

readonly class VolunteerEvent
{
    /** So lange nach Beginn der Veranstaltung nimmt die öffentliche Helferanmeldung noch Anmeldungen an. */
    public const int HELP_REGISTRATION_HOURS_AFTER_START = 24;

    /** @param list<VolunteerEventActivity> $activities */
    public function __construct(
        public string $identifier,
        public string $title,
        public string $date,
        public string $time,
        public array $activities,
    ) {
    }

    /**
     * Ohne lesbares Datum (`Y-m-d`, Uhrzeit optional `H:i`) bleibt die Anmeldung offen, statt eine
     * Veranstaltung wegen unvollständiger Angaben zu sperren.
     */
    public function isHelpRegistrationClosed(\DateTimeImmutable $now): bool
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $this->date.' '.($this->time !== '' ? $this->time : '00:00'), $now->getTimezone());
        if ($start === false) {
            return false;
        }

        return $start->modify(sprintf('+%d hours', self::HELP_REGISTRATION_HOURS_AFTER_START)) <= $now;
    }
}
