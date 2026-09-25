<?php

namespace App\Logic\Rental\Sauna\Booking\Manager;

use App\Logic\Rental\Sauna\Booking\Exception\SaunaBookingNotFoundException;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\SaunaBookingProcessorInterface;
use App\Logic\Rental\Sauna\Booking\SaunaBookingProviderInterface;

readonly class SaunaBookingManager implements SaunaBookingManagerInterface
{
    public function __construct(
        private SaunaBookingProviderInterface $provider,
        private SaunaBookingProcessorInterface $processor,
    ) {
    }

    public function get(string $id): SaunaBooking
    {
        return $this->provider->find($id) ?? throw new SaunaBookingNotFoundException($id);
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->provider->findBetween($from, $to);
    }

    public function save(SaunaBooking $booking): SaunaBooking
    {
        return $this->processor->save($booking);
    }
}
