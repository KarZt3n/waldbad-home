<?php

namespace App\Logic\Rental\Sauna\Booking\Service;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaCalendarDay;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaCalendarSlot;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaSlotState;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;

/**
 * Leitet aus Saison-Wochenplan und vorhandenen Anmeldungen ab, welche Buchungseinheiten frei sind.
 * Offene und angenommene Anmeldungen belegen ihren Zeitraum, abgelehnte geben ihn wieder frei.
 */
readonly class SaunaSlotAvailability
{
    public function __construct(
        private SaunaSeasonManagerInterface $seasons,
        private SaunaBookingManagerInterface $bookings,
    ) {
    }

    public function assertBookable(\DateTimeImmutable $date, string $startTime, string $endTime, \DateTimeImmutable $now): void
    {
        $season = $this->seasons->findCovering($date)
            ?? throw new BusinessRuleViolationException('An diesem Tag ist die Sauna nicht buchbar.');
        if (!$season->isBookableRange($date, $startTime, $endTime)) {
            throw new BusinessRuleViolationException('Der gewählte Zeitraum passt nicht zu den buchbaren Sauna-Zeiten.');
        }
        $this->assertFreeInFuture($date, $startTime, $endTime, $now);
    }

    /**
     * Individuelle Anfragen sind an keine Saison und kein Zeitraster gebunden; sie dürfen nur nicht
     * in der Vergangenheit liegen und keine offene oder angenommene Anmeldung überschneiden.
     */
    public function assertIndividuallyRequestable(\DateTimeImmutable $date, string $startTime, string $endTime, \DateTimeImmutable $now): void
    {
        $this->assertFreeInFuture($date, $startTime, $endTime, $now);
    }

    private function assertFreeInFuture(\DateTimeImmutable $date, string $startTime, string $endTime, \DateTimeImmutable $now): void
    {
        if ($date->setTime(0, 0)->modify($startTime) <= $now) {
            throw new BusinessRuleViolationException('Vergangene Zeiten können nicht gebucht werden.');
        }
        foreach ($this->bookings->findBetween($date, $date) as $booking) {
            if ($booking->blocksTime() && $booking->overlaps($date, $startTime, $endTime)) {
                throw new BusinessRuleViolationException('Der gewählte Zeitraum ist bereits belegt.');
            }
        }
    }

    /**
     * @return list<SaunaCalendarDay>
     */
    public function calendar(\DateTimeImmutable $from, int $days, \DateTimeImmutable $now): array
    {
        $firstDay = $from->setTime(0, 0);
        $lastDay = $firstDay->modify(sprintf('+%d days', $days - 1));
        $seasons = $this->seasons->all();
        $blockingBookings = array_values(array_filter(
            $this->bookings->findBetween($firstDay, $lastDay),
            static fn (SaunaBooking $booking): bool => $booking->blocksTime(),
        ));

        $calendar = [];
        for ($offset = 0; $offset < $days; ++$offset) {
            $day = $firstDay->modify(sprintf('+%d days', $offset));
            $season = $this->seasonFor($seasons, $day);
            $slots = [];
            foreach ($season?->slotsOn($day) ?? [] as $slot) {
                $state = SaunaSlotState::Free;
                if ($slot->startsAt() <= $now) {
                    $state = SaunaSlotState::Past;
                } else {
                    foreach ($blockingBookings as $booking) {
                        if ($booking->overlaps($day, $slot->startTime, $slot->endTime)) {
                            $state = SaunaSlotState::Booked;
                            break;
                        }
                    }
                }
                $slots[] = new SaunaCalendarSlot($slot->startTime, $slot->endTime, $state);
            }
            $calendar[] = new SaunaCalendarDay($day, $slots);
        }

        return $calendar;
    }

    /**
     * @param list<SaunaSeason> $seasons
     */
    private function seasonFor(array $seasons, \DateTimeImmutable $day): ?SaunaSeason
    {
        $covering = null;
        foreach ($seasons as $season) {
            if ($season->covers($day) && ($covering === null || $season->startsOn >= $covering->startsOn)) {
                $covering = $season;
            }
        }

        return $covering;
    }
}
