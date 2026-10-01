<?php

namespace App\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitSaunaBookingRequest;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Mapping\SaunaBookingModelFactory;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;
use App\Logic\Rental\Sauna\Booking\Service\SaunaSlotAvailability;
use App\Logic\Rental\Sauna\Terms\Manager\SaunaTermsManagerInterface;

/** Anfrage für freie Buchungseinheiten aus dem Kalender (individuelle Anfragen: `SubmitIndividualSaunaRequestUseCase`). */
readonly class SubmitSaunaBookingUseCase
{
    public function __construct(
        private SaunaBookingModelFactory $factory,
        private SaunaSlotAvailability $availability,
        private SaunaTermsManagerInterface $terms,
        private SaunaBookingManagerInterface $manager,
        private SaunaBookingNotifierInterface $notifier,
    ) {
    }

    public function execute(SubmitSaunaBookingRequest $request): SaunaBookingResponse
    {
        $terms = $this->terms->current();
        $terms->assertGroupSize($request->personCount);
        $booking = $this->factory->createFromRequest($request, $terms);
        $terms->assertMinimumDuration($booking->durationMinutes());
        $this->availability->assertBookable($booking->date, $booking->startTime, $booking->endTime, $booking->submittedAt);

        $saved = $this->manager->save($booking);
        $this->notifier->bookingSubmitted($saved);

        return SaunaBookingResponse::fromBooking($saved);
    }
}
