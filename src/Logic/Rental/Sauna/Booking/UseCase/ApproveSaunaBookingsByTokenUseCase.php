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
use App\Logic\Rental\Sauna\Booking\Service\SaunaBookingAcceptance;

/**
 * Nimmt über den Link aus der Benachrichtigung alle noch offenen Termine der Anfrage an — wie
 * „Alle annehmen“ in der Redaktion, inklusive Bestätigung an die anfragende Person. Inzwischen in
 * der Redaktion abgelehnte oder angenommene Termine bleiben unverändert. Der Link ist danach verbraucht.
 */
readonly class ApproveSaunaBookingsByTokenUseCase
{
    public function __construct(
        private SaunaBookingApprovalTokenManagerInterface $tokens,
        private SaunaBookingManagerInterface $bookings,
        private SaunaBookingAcceptance $acceptance,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<SaunaBookingResponse> die angenommenen Termine */
    public function execute(string $rawToken, bool $dutyRequired): array
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

        $accepted = $this->acceptance->accept($open, $dutyRequired);
        $this->tokens->save($token->markUsed($now));

        return array_map(SaunaBookingResponse::fromBooking(...), $accepted);
    }
}
