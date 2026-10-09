<?php

namespace App\Logic\Rental\Sauna\Duty\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Eintrag im Sauna-Dienstplan: Eine Person übernimmt zum Zeitpunkt `$time` an `$date` den Dienst
 * (Einlass und Abkassieren, Übergabe zwischen zwei Buchungen oder Abschließen). Name und
 * Mitgliedsnummer sind ein Snapshot zum Zeitpunkt des Eintragens, damit der Dienstplan ohne Zugriff auf das Mitgliedermodul angezeigt werden kann.
 */
readonly class SaunaDuty
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $date,
        public string $time,
        /** Verweis auf `Member::$id`, ohne DB-Fremdschlüssel (analog zu `SaunaBooking::$memberId`). */
        public string $memberId,
        public string $memberNumber,
        public string $firstName,
        public string $lastName,
    ) {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $this->time) !== 1) {
            throw new BusinessRuleViolationException('Die Uhrzeit des Saunadienstes muss im Format HH:MM angegeben werden.');
        }
        if (trim($this->firstName) === '' || trim($this->lastName) === '') {
            throw new BusinessRuleViolationException('Für den Saunadienst ist eine Person erforderlich.');
        }
    }
}
