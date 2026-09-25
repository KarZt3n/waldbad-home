<?php

namespace App\Logic\Rental\Sauna\Booking;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;

interface SaunaBookingProcessorInterface
{
    public function save(SaunaBooking $booking): SaunaBooking;
}
