<?php

namespace App\Logic\Rental\Sauna\Booking;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;

interface SaunaBookingProviderInterface
{
    public function find(string $id): ?SaunaBooking;

    /** @return list<SaunaBooking> nach Datum und Beginn aufsteigend sortiert */
    public function findAll(): array;

    /** @return list<SaunaBooking> Anmeldungen mit Datum im geschlossenen Intervall [$from, $to] */
    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
