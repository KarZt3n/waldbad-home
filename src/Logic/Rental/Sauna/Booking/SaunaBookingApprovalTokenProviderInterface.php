<?php

namespace App\Logic\Rental\Sauna\Booking;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingApprovalToken;

interface SaunaBookingApprovalTokenProviderInterface
{
    public function findByHash(string $tokenHash): ?SaunaBookingApprovalToken;
}
