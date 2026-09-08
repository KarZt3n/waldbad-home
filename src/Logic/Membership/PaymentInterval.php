<?php

namespace App\Logic\Membership;

/**
 * Zeitraum, auf den sich ein Beitragssatz bezieht bzw. in dem ein Mitglied seinen Beitrag zahlt.
 * Wird von `ContributionRate` (Zeitraum) und `Member` (Zahlintervall) gemeinsam genutzt, da beide
 * dieselben Perioden desselben Membership-Moduls beschreiben. `Once` ist nur für Beitragssätze
 * relevant (z. B. eine einmalige Beitrittsgebühr), nicht als Zahlintervall eines Mitglieds.
 */
enum PaymentInterval: string
{
    case Once = 'once';
    case Yearly = 'yearly';
    case HalfYearly = 'half_yearly';
    case Quarterly = 'quarterly';
    case BiMonthly = 'bimonthly';
    case Monthly = 'monthly';

    public function occurrencesPerYear(): int
    {
        return match ($this) {
            self::Once, self::Yearly => 1,
            self::HalfYearly => 2,
            self::Quarterly => 4,
            self::BiMonthly => 6,
            self::Monthly => 12,
        };
    }
}
