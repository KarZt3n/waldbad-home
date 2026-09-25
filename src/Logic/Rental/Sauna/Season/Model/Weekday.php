<?php

namespace App\Logic\Rental\Sauna\Season\Model;

/** ISO-8601-Wochentage (Montag = 1 … Sonntag = 7), passend zu `DateTimeInterface::format('N')`. */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public static function fromDate(\DateTimeImmutable $date): self
    {
        return self::from((int) $date->format('N'));
    }
}
