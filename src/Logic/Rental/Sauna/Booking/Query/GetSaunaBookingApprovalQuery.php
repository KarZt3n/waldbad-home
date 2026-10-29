<?php

namespace App\Logic\Rental\Sauna\Booking\Query;

use App\Logic\Common\ClockInterface;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Exception\InvalidSaunaBookingApprovalTokenException;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingApprovalTokenManagerInterface;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;

/** Termine hinter einem Link „Anfrage annehmen“, damit die Seite vor dem Annehmen zeigt, worum es geht. */
readonly class GetSaunaBookingApprovalQuery
{
    public function __construct(
        private SaunaBookingApprovalTokenManagerInterface $tokens,
        private SaunaBookingManagerInterface $bookings,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<SaunaBookingResponse> */
    public function execute(string $rawToken): array
    {
        $token = $this->tokens->findByHash(hash('sha256', $rawToken));
        if ($token === null || !$token->isUsable($this->clock->now())) {
            throw new InvalidSaunaBookingApprovalTokenException();
        }

        return array_map(
            fn (string $id): SaunaBookingResponse => SaunaBookingResponse::fromBooking($this->bookings->get($id)),
            $token->bookingIds,
        );
    }
}
