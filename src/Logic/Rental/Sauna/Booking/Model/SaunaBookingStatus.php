<?php

namespace App\Logic\Rental\Sauna\Booking\Model;

enum SaunaBookingStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
