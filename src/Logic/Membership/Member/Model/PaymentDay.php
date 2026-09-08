<?php

namespace App\Logic\Membership\Member\Model;

/**
 * Tag im Monat, zu dem die Beitragszahlung eines Mitglieds fällig wird (Radiobutton-Auswahl im
 * Overlay: „zum 01.“ oder „zum 15.“).
 */
enum PaymentDay: string
{
    case First = 'first';
    case Fifteenth = 'fifteenth';

    public function dayOfMonth(): int
    {
        return match ($this) {
            self::First => 1,
            self::Fifteenth => 15,
        };
    }
}
