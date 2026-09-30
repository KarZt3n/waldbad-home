<?php

namespace App\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;
use App\Logic\Rental\Sauna\Booking\Service\SaunaRequesterEmailResolver;

/**
 * Storniert einen angenommenen Sauna-Termin; der Zeitraum wird im Kalender wieder frei. Auf Wunsch
 * (`$notify`) erhält die anfragende Person eine Stornobestätigung — ohne bekannte E-Mail-Adresse
 * wird nichts versendet.
 */
readonly class CancelSaunaBookingUseCase
{
    public function __construct(
        private SaunaBookingManagerInterface $manager,
        private SaunaRequesterEmailResolver $recipient,
        private SaunaBookingNotifierInterface $notifier,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id, bool $notify): SaunaBookingResponse
    {
        $saved = $this->manager->save($this->manager->get($id)->cancel($this->clock->now()));
        $recipientEmail = $notify ? $this->recipient->resolve([$saved]) : null;
        if ($recipientEmail !== null) {
            $this->notifier->bookingCancelled($saved, $recipientEmail);
        }

        return SaunaBookingResponse::fromBooking($saved);
    }
}
