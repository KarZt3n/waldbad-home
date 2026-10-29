<?php

namespace App\Logic\Rental\Sauna\Duty\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Vermerk „kein Dienst nötig“: Die Gäste kommen allein herein und gehen wieder, ohne dass jemand vom
 * Verein vor Ort sein muss. Ohne `$time` gilt der Vermerk für alle Dienstzeitpunkte des Tages.
 */
readonly class SaunaDutyWaiver
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $date,
        public ?string $time,
    ) {
        if ($this->time !== null && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $this->time) !== 1) {
            throw new BusinessRuleViolationException('Die Uhrzeit muss im Format HH:MM angegeben werden.');
        }
    }

    public function coversWholeDay(): bool
    {
        return $this->time === null;
    }
}
