<?php

namespace App\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;

/** Ändert nach dem Annehmen, ob für eine Buchung Sauna-Dienst nötig ist (siehe `SaunaBooking::$dutyRequired`). */
readonly class ChangeSaunaBookingDutyUseCase
{
    public function __construct(
        private SaunaBookingManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id, bool $dutyRequired): SaunaBookingResponse
    {
        $booking = $this->manager->get($id);

        return SaunaBookingResponse::fromBooking($this->manager->save($booking->withDutyRequired($dutyRequired, $this->clock->now())));
    }
}
