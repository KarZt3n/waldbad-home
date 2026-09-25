<?php

namespace App\Logic\Rental\Sauna\Season\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/** Buchbares Zeitfenster der Sauna an einem Wochentag, z. B. Montag 16:00–21:00. */
readonly class SaunaOpeningHours
{
    public function __construct(
        public Weekday $weekday,
        public string $startTime,
        public string $endTime,
    ) {
        if (!self::isValidTime($this->startTime) || !self::isValidTime($this->endTime)) {
            throw new BusinessRuleViolationException('Die Zeiten der Sauna müssen im Format HH:MM angegeben werden.');
        }
        if ($this->startMinute() >= $this->endMinute()) {
            throw new BusinessRuleViolationException('Das Ende eines Sauna-Zeitfensters muss nach dessen Beginn liegen.');
        }
    }

    public function startMinute(): int
    {
        return self::toMinutes($this->startTime);
    }

    public function endMinute(): int
    {
        return self::toMinutes($this->endTime);
    }

    public function overlaps(self $other): bool
    {
        return $this->weekday === $other->weekday
            && $this->startMinute() < $other->endMinute()
            && $other->startMinute() < $this->endMinute();
    }

    public static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    public static function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private static function isValidTime(string $time): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }
}
