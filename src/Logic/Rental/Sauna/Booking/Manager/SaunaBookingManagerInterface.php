<?php

namespace App\Logic\Rental\Sauna\Booking\Manager;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;

interface SaunaBookingManagerInterface
{
    public function get(string $id): SaunaBooking;

    /** @return list<SaunaBooking> */
    public function all(): array;

    /** @return list<SaunaBooking> */
    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array;

    public function save(SaunaBooking $booking): SaunaBooking;
}
