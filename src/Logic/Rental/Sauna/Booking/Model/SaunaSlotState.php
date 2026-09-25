<?php

namespace App\Logic\Rental\Sauna\Booking\Model;

enum SaunaSlotState: string
{
    case Free = 'free';
    case Booked = 'booked';
    case Past = 'past';
}
