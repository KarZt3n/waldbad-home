<?php

namespace App\Logic\Rental\Sauna\Booking\Service;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;

/**
 * Gemeinsame Annahme einzelner Sauna-Anmeldungen und ganzer individueller Anfragen: Ein Zeitraum
 * darf nur einmal angenommen sein; die anfragende Person erhält eine Bestätigung mit allen
 * angenommenen Tagen (Empfänger siehe `SaunaRequesterEmailResolver`). Ohne Adresse wird nichts
 * versendet.
 */
readonly class SaunaBookingAcceptance
{
    public function __construct(
        private SaunaBookingManagerInterface $manager,
        private SaunaRequesterEmailResolver $recipient,
        private SaunaBookingNotifierInterface $notifier,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param non-empty-list<SaunaBooking> $bookings
     *
     * @return non-empty-list<SaunaBooking>
     */
    public function accept(array $bookings): array
    {
        $problems = [];
        foreach ($bookings as $booking) {
            if ($this->isBlockedByAcceptedBooking($booking)) {
                $problems[] = sprintf('%s, %s–%s Uhr', $booking->date->format('d.m.Y'), $booking->startTime, $booking->endTime);
            }
        }
        if ($problems !== []) {
            throw new BusinessRuleViolationException(sprintf(
                'Der Zeitraum ist bereits durch eine andere angenommene Anmeldung belegt: %s.',
                implode('; ', $problems),
            ));
        }

        $now = $this->clock->now();
        $saved = $this->manager->saveAll(array_map(static fn (SaunaBooking $booking): SaunaBooking => $booking->accept($now), $bookings));
        if ($saved === []) {
            throw new \LogicException('Angenommene Sauna-Anmeldungen müssen gespeichert zurückgegeben werden.');
        }
        $recipientEmail = $this->recipient->resolve($saved);
        if ($recipientEmail !== null) {
            $this->notifier->bookingsAccepted($saved, $recipientEmail);
        }

        return $saved;
    }

    private function isBlockedByAcceptedBooking(SaunaBooking $booking): bool
    {
        foreach ($this->manager->findBetween($booking->date, $booking->date) as $other) {
            if ($other->id !== $booking->id
                && $other->status === SaunaBookingStatus::Accepted
                && $other->overlaps($booking->date, $booking->startTime, $booking->endTime)) {
                return true;
            }
        }

        return false;
    }
}
