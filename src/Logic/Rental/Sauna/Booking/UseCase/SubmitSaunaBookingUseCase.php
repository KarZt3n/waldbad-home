<?php

namespace App\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitSaunaBookingRequest;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Mapping\SaunaBookingModelFactory;
use App\Logic\Rental\Sauna\Booking\Service\SaunaSlotAvailability;
use App\Logic\Rental\Sauna\Terms\Manager\SaunaTermsManagerInterface;

readonly class SubmitSaunaBookingUseCase
{
    public function __construct(
        private SaunaBookingModelFactory $factory,
        private SaunaSlotAvailability $availability,
        private SaunaTermsManagerInterface $terms,
        private SaunaBookingManagerInterface $manager,
    ) {
    }

    public function execute(SubmitSaunaBookingRequest $request): SaunaBookingResponse
    {
        $terms = $this->terms->current();
        $terms->assertGroupSize($request->personCount);
        $booking = $this->factory->createFromRequest($request, $terms);
        if ($booking->individual) {
            $this->availability->assertIndividuallyRequestable($booking->date, $booking->startTime, $booking->endTime, $booking->submittedAt);
        } else {
            $this->availability->assertBookable($booking->date, $booking->startTime, $booking->endTime, $booking->submittedAt);
        }

        return SaunaBookingResponse::fromBooking($this->manager->save($booking));
    }
}
