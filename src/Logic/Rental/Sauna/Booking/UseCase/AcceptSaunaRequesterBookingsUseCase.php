<?php

namespace App\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\Service\SaunaBookingAcceptance;

/**
 * „Alle annehmen“ für eine anfragende Person (siehe `SaunaBooking::requesterKey()`): nimmt alle ihre
 * noch offenen Anmeldungen — auch aus mehreren Anfragen — gemeinsam oder gar nicht an (bereits
 * abgelehnte bleiben abgelehnt); sie erhält eine einzige Bestätigung mit allen angenommenen Tagen.
 */
readonly class AcceptSaunaRequesterBookingsUseCase
{
    public function __construct(
        private SaunaBookingManagerInterface $manager,
        private SaunaBookingAcceptance $acceptance,
    ) {
    }

    /**
     * @return list<SaunaBookingResponse>
     */
    public function execute(string $requesterKey): array
    {
        $open = array_values(array_filter(
            $this->manager->all(),
            static fn (SaunaBooking $booking): bool => $booking->status === SaunaBookingStatus::Open && $booking->requesterKey() === $requesterKey,
        ));
        if ($open === []) {
            throw new BusinessRuleViolationException('Für diese Person gibt es keine offenen Sauna-Anmeldungen mehr.');
        }

        return array_map(SaunaBookingResponse::fromBooking(...), $this->acceptance->accept($open));
    }
}
