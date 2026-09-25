<?php

namespace App\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;

readonly class AcceptSaunaBookingUseCase
{
    public function __construct(
        private SaunaBookingManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id): SaunaBookingResponse
    {
        $booking = $this->manager->get($id);
        foreach ($this->manager->findBetween($booking->date, $booking->date) as $other) {
            if ($other->id !== $booking->id
                && $other->status === SaunaBookingStatus::Accepted
                && $other->overlaps($booking->date, $booking->startTime, $booking->endTime)) {
                throw new BusinessRuleViolationException('Der Zeitraum ist bereits durch eine andere angenommene Anmeldung belegt.');
            }
        }

        return SaunaBookingResponse::fromBooking($this->manager->save($booking->accept($this->clock->now())));
    }
}
