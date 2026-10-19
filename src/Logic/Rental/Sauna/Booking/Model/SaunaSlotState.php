<?php

namespace App\Logic\Rental\Sauna\Booking\Model;

enum SaunaSlotState: string
{
    case Free = 'free';
    case Booked = 'booked';
    case Past = 'past';
    /** Noch nicht vergangen, aber innerhalb des Vorlaufs (`SaunaTerms::$leadDays`) nicht mehr anfragbar. */
    case TooSoon = 'too_soon';
}
