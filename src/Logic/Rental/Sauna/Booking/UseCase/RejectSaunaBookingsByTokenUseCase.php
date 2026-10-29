<?php

namespace App\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Exception\InvalidSaunaBookingApprovalTokenException;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingApprovalTokenManagerInterface;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;

/**
 * Lehnt über den Link aus der Benachrichtigung alle noch offenen Termine der Anfrage ab — wie
 * „Ablehnen“ in der Redaktion ohne Mail an die anfragende Person. Der Link ist danach verbraucht,
 * auch für „Annehmen“.
 */
readonly class RejectSaunaBookingsByTokenUseCase
{
    public function __construct(
        private SaunaBookingApprovalTokenManagerInterface $tokens,
        private SaunaBookingManagerInterface $bookings,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<SaunaBookingResponse> die abgelehnten Termine */
    public function execute(string $rawToken): array
    {
        $now = $this->clock->now();
        $token = $this->tokens->findByHash(hash('sha256', $rawToken));
        if ($token === null || !$token->isUsable($now)) {
            throw new InvalidSaunaBookingApprovalTokenException();
        }
        $open = array_values(array_filter(
            array_map($this->bookings->get(...), $token->bookingIds),
            static fn (SaunaBooking $booking): bool => $booking->status === SaunaBookingStatus::Open,
        ));
        if ($open === []) {
            throw new BusinessRuleViolationException('Diese Anfrage wurde bereits in der Redaktion bearbeitet — es gibt keine offenen Termine mehr.');
        }

        $rejected = $this->bookings->saveAll(array_map(static fn (SaunaBooking $booking): SaunaBooking => $booking->reject($now), $open));
        $this->tokens->save($token->markUsed($now));

        return array_map(SaunaBookingResponse::fromBooking(...), $rejected);
    }
}
