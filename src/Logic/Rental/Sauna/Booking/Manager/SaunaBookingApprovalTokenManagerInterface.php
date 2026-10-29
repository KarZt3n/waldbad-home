<?php

namespace App\Logic\Rental\Sauna\Booking\Manager;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingApprovalToken;

interface SaunaBookingApprovalTokenManagerInterface
{
    public function findByHash(string $tokenHash): ?SaunaBookingApprovalToken;

    public function save(SaunaBookingApprovalToken $token): void;
}
