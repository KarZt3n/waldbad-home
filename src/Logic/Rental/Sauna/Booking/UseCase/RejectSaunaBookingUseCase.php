<?php

namespace App\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;

readonly class RejectSaunaBookingUseCase
{
    public function __construct(
        private SaunaBookingManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id): SaunaBookingResponse
    {
        $booking = $this->manager->get($id)->reject($this->clock->now());

        return SaunaBookingResponse::fromBooking($this->manager->save($booking));
    }
}
