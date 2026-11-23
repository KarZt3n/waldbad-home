<?php

namespace App\Logic\Event\HelpRequest\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class ParticipationInterval
{
    private const int END_OF_DAY_MINUTE = 24 * 60;

    /** Bei einem offenen Zeitraum (Person ist noch anwesend) 0, bis sie sich austrägt. */
    public int $minutes;

    public function __construct(
        public string $id,
        public int $position,
        public string $fromTime,
        public ?string $toTime,
    ) {
        $from = \DateTimeImmutable::createFromFormat('!H:i', $this->fromTime);
        if ($this->position < 0 || $from === false || $from->format('H:i') !== $this->fromTime) {
            throw new BusinessRuleViolationException('Jeder Hilfezeitraum benötigt eine gültige Von- und Bis-Uhrzeit am selben Tag.');
        }
        if ($this->toTime === null) {
            $this->minutes = 0;

            return;
        }
        $to = \DateTimeImmutable::createFromFormat('!H:i', $this->toTime);
        if ($to === false || $to->format('H:i') !== $this->toTime || $to <= $from) {
            throw new BusinessRuleViolationException('Jeder Hilfezeitraum benötigt eine gültige Von- und Bis-Uhrzeit am selben Tag.');
        }
        $this->minutes = (int) (($to->getTimestamp() - $from->getTimestamp()) / 60);
    }

    public function isOpen(): bool
    {
        return $this->toTime === null;
    }

    public function startsAtMinute(): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $this->fromTime));

        return $hours * 60 + $minutes;
    }

    /** Ein offener Zeitraum reicht bis zum Tagesende, damit kein späterer Zeitraum ihn überschneiden kann. */
    public function endsAtMinute(): int
    {
        return $this->isOpen() ? self::END_OF_DAY_MINUTE : $this->startsAtMinute() + $this->minutes;
    }
}
