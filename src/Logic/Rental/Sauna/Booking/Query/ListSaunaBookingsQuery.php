<?php

namespace App\Logic\Rental\Sauna\Booking\Query;

use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\SaunaGuestDirectoryInterface;

readonly class ListSaunaBookingsQuery
{
    public function __construct(
        private SaunaBookingManagerInterface $manager,
        private SaunaGuestDirectoryInterface $guestDirectory,
    ) {
    }

    /** @return list<SaunaBookingResponse> */
    public function execute(): array
    {
        $bookings = $this->manager->all();
        $memberIds = array_values(array_filter(array_map(
            static fn (SaunaBooking $booking): ?string => $booking->memberId,
            $bookings,
        ), static fn (?string $memberId): bool => $memberId !== null));
        $contacts = $this->guestDirectory->contacts($memberIds);

        return array_map(
            static fn (SaunaBooking $booking): SaunaBookingResponse => SaunaBookingResponse::fromBooking(
                $booking,
                $booking->memberId === null ? null : ($contacts[$booking->memberId] ?? null),
            ),
            $bookings,
        );
    }
}
