<?php

namespace App\Data\Overview\Adapter;

use App\Logic\Overview\Model\OverviewTaskType;
use App\Logic\Overview\OverviewTaskSourceInterface;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;

readonly class SaunaBookingTaskSource implements OverviewTaskSourceInterface
{
    public function __construct(private SaunaBookingManagerInterface $bookings)
    {
    }

    public function type(): OverviewTaskType
    {
        return OverviewTaskType::SaunaBookings;
    }

    public function openItemDates(): array
    {
        return array_values(array_map(
            static fn (SaunaBooking $booking): \DateTimeImmutable => $booking->submittedAt,
            array_filter(
                $this->bookings->all(),
                static fn (SaunaBooking $booking): bool => $booking->status === SaunaBookingStatus::Open,
            ),
        ));
    }
}
