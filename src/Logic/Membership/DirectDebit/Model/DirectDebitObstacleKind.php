<?php

namespace App\Logic\Membership\DirectDebit\Model;

/**
 * Kategorie eines Grundes, warum eine Lastschrift nicht exportiert werden kann — für die
 * Gruppierung in der Lastschrift-Übersicht. Die Reihenfolge der Fälle ist die Anzeigereihenfolge.
 */
enum DirectDebitObstacleKind: string
{
    case MissingBankAccount = 'missing_bank_account';
    case MissingMandate = 'missing_mandate';
    case ExpiredMandate = 'expired_mandate';
    case AlreadyCollected = 'already_collected';
    case OtherPaymentMethod = 'other_payment_method';
    case MissingPayer = 'missing_payer';
    case IncompleteCreditor = 'incomplete_creditor';
    case InvalidRemittance = 'invalid_remittance';
    case ContributionNotCalculated = 'contribution_not_calculated';

    public function label(): string
    {
        return match ($this) {
            self::MissingBankAccount => 'Ohne Bankverbindung',
            self::MissingMandate => 'Ohne Mandat',
            self::ExpiredMandate => 'Mandat abgelaufen',
            self::AlreadyCollected => 'Jahr bereits exportiert',
            self::OtherPaymentMethod => 'Andere Zahlart',
            self::MissingPayer => 'Ohne Zahler',
            self::IncompleteCreditor => 'Gläubigerdaten unvollständig',
            self::InvalidRemittance => 'Verwendungszweck ungültig',
            self::ContributionNotCalculated => 'Beitrag nicht für das Jahr berechnet',
        };
    }
}
