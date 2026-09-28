<?php

namespace App\Logic\Membership\DirectDebit\Model;

/**
 * Lastschriftsequenz laut SEPA-Regelwerk. Seit November 2016 darf auch die erste Lastschrift
 * eines Mandats als Folgelastschrift eingereicht werden; die Erstlastschrift bleibt für Banken
 * wählbar, die sie weiterhin erwarten.
 */
enum SequenceType: string
{
    case First = 'FRST';
    case Recurring = 'RCUR';

    public function label(): string
    {
        return match ($this) {
            self::First => 'Erstlastschrift (FRST)',
            self::Recurring => 'Folgelastschrift (RCUR)',
        };
    }
}
