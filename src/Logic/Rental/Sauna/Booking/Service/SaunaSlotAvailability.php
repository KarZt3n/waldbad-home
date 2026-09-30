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
 * Offene und angenommene Anmeldungen belegen ihren Zeitraum, abgelehnte geben ihn wieder frei. An
 * Schließzeiten der Saison ist die Sauna geschlossen — auch für individuelle Anfragen.
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
        $season = $this->seasonCovering($date);
        $this->assertNotClosed($season, $date);
        if (!$season->isBookableRange($date, $startTime, $endTime)) {
            throw new BusinessRuleViolationException('Der gewählte Zeitraum passt nicht zu den buchbaren Sauna-Zeiten.');
        }
        $this->assertFreeInFuture($date, $startTime, $endTime, $now);
    }

    /**
     * Individuelle Anfragen sind an kein Zeitraster gebunden, wohl aber an die Saison: Der Tag muss
     * in einer Saison und außerhalb ihrer Schließzeiten liegen, darf nicht vergangen sein und keine
     * offene oder angenommene Anmeldung überschneiden.
     */
    public function assertIndividuallyRequestable(\DateTimeImmutable $date, string $startTime, string $endTime, \DateTimeImmutable $now): void
    {
        $this->assertNotClosed($this->seasonCovering($date), $date);
        $this->assertFreeInFuture($date, $startTime, $endTime, $now);
    }

    private function seasonCovering(\DateTimeImmutable $date): SaunaSeason
    {
        return $this->seasons->findCovering($date)
            ?? throw new BusinessRuleViolationException(sprintf('Der %s liegt außerhalb der Sauna-Saison.', $date->format('d.m.Y')));
    }

    private function assertNotClosed(SaunaSeason $season, \DateTimeImmutable $date): void
    {
        $closure = $season->closureOn($date);
        if ($closure !== null) {
            throw new BusinessRuleViolationException($closure->messageFor($date));
        }
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
            $closure = $season?->closureOn($day);
            $calendar[] = new SaunaCalendarDay($day, $slots, $closure !== null, $closure->reason ?? '');
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
