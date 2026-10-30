<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Model;

/**
 * Kategorie eines Grundes, warum die Gutschrift eines Haushalts nicht überwiesen werden kann — für
 * die Gruppierung in der Übersicht. Die Reihenfolge der Fälle ist die Anzeigereihenfolge.
 */
enum WorkAssignmentRefundObstacleKind: string
{
    case MissingBankAccount = 'missing_bank_account';
    case MissingPayer = 'missing_payer';
    case MultiplePayers = 'multiple_payers';
    case IncompleteClubAccount = 'incomplete_club_account';

    public function label(): string
    {
        return match ($this) {
            self::MissingBankAccount => 'Ohne Bankverbindung',
            self::MissingPayer => 'Ohne Zahler',
            self::MultiplePayers => 'Mehrere Zahler',
            self::IncompleteClubAccount => 'Vereinskonto unvollständig',
        };
    }
}
