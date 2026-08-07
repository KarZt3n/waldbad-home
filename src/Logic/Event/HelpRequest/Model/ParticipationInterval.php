<?php

namespace App\Logic\Event\HelpRequest\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class ParticipationInterval
{
    public int $minutes;

    public function __construct(
        public string $id,
        public int $position,
        public string $fromTime,
        public string $toTime,
    ) {
        $from = \DateTimeImmutable::createFromFormat('!H:i', $this->fromTime);
        $to = \DateTimeImmutable::createFromFormat('!H:i', $this->toTime);
        if ($this->position < 0 || $from === false || $to === false
            || $from->format('H:i') !== $this->fromTime || $to->format('H:i') !== $this->toTime
            || $to <= $from) {
            throw new BusinessRuleViolationException('Jeder Hilfezeitraum benötigt eine gültige Von- und Bis-Uhrzeit am selben Tag.');
        }
        $this->minutes = (int) (($to->getTimestamp() - $from->getTimestamp()) / 60);
    }

    public function startsAtMinute(): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $this->fromTime));

        return $hours * 60 + $minutes;
    }

    public function endsAtMinute(): int
    {
        return $this->startsAtMinute() + $this->minutes;
    }
}
