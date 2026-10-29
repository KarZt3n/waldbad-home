<?php

namespace App\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Service\SaunaBookingAcceptance;

/** Nimmt eine einzelne Sauna-Anmeldung (bzw. einen einzelnen Wunschtag) an, siehe `SaunaBookingAcceptance`. */
readonly class AcceptSaunaBookingUseCase
{
    public function __construct(
        private SaunaBookingManagerInterface $manager,
        private SaunaBookingAcceptance $acceptance,
    ) {
    }

    public function execute(string $id, bool $dutyRequired = true): SaunaBookingResponse
    {
        return SaunaBookingResponse::fromBooking($this->acceptance->accept([$this->manager->get($id)], $dutyRequired)[0]);
    }
}
