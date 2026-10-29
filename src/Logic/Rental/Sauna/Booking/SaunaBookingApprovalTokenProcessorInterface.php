<?php

namespace App\Logic\Rental\Sauna\Booking;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingApprovalToken;

interface SaunaBookingApprovalTokenProcessorInterface
{
    public function save(SaunaBookingApprovalToken $token): void;
}
