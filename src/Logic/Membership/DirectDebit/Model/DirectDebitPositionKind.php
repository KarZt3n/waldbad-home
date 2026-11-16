<?php

namespace App\Logic\Membership\DirectDebit\Model;

/**
 * Art einer Lastschrift-Position — zugleich die Art eines Exports: Mitgliedsbeiträge,
 * Arbeitseinsatz-Pauschale und Einmalgebühren werden jeweils getrennt exportiert.
 */
enum DirectDebitPositionKind: string
{
    case Contribution = 'contribution';
    case WorkAssignmentSurcharge = 'work_assignment_surcharge';
    case OneTimeCharge = 'one_time_charge';

    public function label(): string
    {
        return match ($this) {
            self::Contribution => 'Mitgliedsbeiträge',
            self::WorkAssignmentSurcharge => 'Arbeitseinsatz-Pauschale',
            self::OneTimeCharge => 'Einmalgebühren',
        };
    }

    public function fileSuffix(): string
    {
        return match ($this) {
            self::Contribution => 'beitraege',
            self::WorkAssignmentSurcharge => 'arbeitseinsatz',
            self::OneTimeCharge => 'einmalgebuehren',
        };
    }

    /** Kürzel für Message-ID und End-to-End-ID. */
    public function code(): string
    {
        return match ($this) {
            self::Contribution => 'B',
            self::WorkAssignmentSurcharge => 'A',
            self::OneTimeCharge => 'E',
        };
    }
}
