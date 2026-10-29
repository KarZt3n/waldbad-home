<?php

namespace App\Logic\Rental\Sauna\Duty\Service;

use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyBookingResponse;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyPoint;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyResponse;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyRosterDay;
use App\Logic\Rental\Sauna\Duty\Manager\SaunaDutyManagerInterface;
use App\Logic\Rental\Sauna\Duty\Manager\SaunaDutyWaiverManagerInterface;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDuty;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyPointKind;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyPointState;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyWaiver;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;

/**
 * Leitet die Dienstzeitpunkte des Dienstplans ab. Ein Dienst ist kein Zeitraum, sondern ein Zeitpunkt:
 * Einlass zu Beginn einer Buchung, Übergabe, wenn eine Buchung endet und die nächste beginnt, und
 * Abschließen, wenn eine Buchung ohne direkte Folgebuchung endet. Als Zeitpunkte erscheinen alle
 * Grenzen der Buchungseinheiten laut Saison (z. B. 15:00, 17:00, …, 23:00) sowie Beginn und Ende
 * jeder offenen oder angenommenen Buchung. Liegt ein Zeitpunkt mitten in einer Buchung
 * (z. B. 17:00 bei 15:00–19:00), findet kein Wechsel statt — er gilt als „läuft“. Vermerke „kein
 * Dienst nötig“ (je Zeitpunkt oder ganzer Tag) ändern Status und Art nicht, nur `dutyWaived`.
 */
readonly class SaunaDutyRoster
{
    public function __construct(
        private SaunaSeasonManagerInterface $seasons,
        private SaunaBookingManagerInterface $bookings,
        private SaunaDutyManagerInterface $duties,
        private SaunaDutyWaiverManagerInterface $waivers,
    ) {
    }

    /** @return list<SaunaDutyRosterDay> */
    public function days(\DateTimeImmutable $from, int $count): array
    {
        $firstDay = $from->setTime(0, 0);
        $lastDay = $firstDay->modify(sprintf('+%d days', $count - 1));
        $seasons = $this->seasons->all();
        $bookings = $this->bookings->findBetween($firstDay, $lastDay);
        $duties = $this->duties->findBetween($firstDay, $lastDay);
        $waivers = $this->waivers->findBetween($firstDay, $lastDay);

        $days = [];
        for ($offset = 0; $offset < $count; ++$offset) {
            $day = $firstDay->modify(sprintf('+%d days', $offset));
            $season = $this->seasonFor($seasons, $day);
            $closure = $season?->closureOn($day);
            $isoDay = $day->format('Y-m-d');
            $dayWaivers = array_values(array_filter($waivers, static fn (SaunaDutyWaiver $waiver): bool => $waiver->date->format('Y-m-d') === $isoDay));
            $wholeDayWaiver = array_find($dayWaivers, static fn (SaunaDutyWaiver $waiver): bool => $waiver->coversWholeDay());
            $days[] = new SaunaDutyRosterDay(
                $day,
                $this->points($season, $day, $bookings, $duties, $dayWaivers, $wholeDayWaiver !== null),
                $closure !== null,
                $closure->reason ?? '',
                $wholeDayWaiver?->id,
            );
        }

        return $days;
    }

    /** @return list<SaunaDutyPoint> */
    public function pointsOn(\DateTimeImmutable $date): array
    {
        return $this->days($date, 1)[0]->points;
    }

    /**
     * @param list<SaunaBooking> $bookings
     * @param list<SaunaDuty>       $duties
     * @param list<SaunaDutyWaiver> $dayWaivers Vermerke dieses Tages
     *
     * @return list<SaunaDutyPoint>
     */
    private function points(?SaunaSeason $season, \DateTimeImmutable $day, array $bookings, array $duties, array $dayWaivers, bool $wholeDayWaived): array
    {
        $isoDay = $day->format('Y-m-d');
        $dayBookings = array_values(array_filter(
            $bookings,
            static fn (SaunaBooking $booking): bool => $booking->blocksTime() && $booking->date->format('Y-m-d') === $isoDay,
        ));
        $dayDuties = array_values(array_filter($duties, static fn (SaunaDuty $duty): bool => $duty->date->format('Y-m-d') === $isoDay));

        $gridTimes = [];
        foreach ($season?->slotsOn($day) ?? [] as $slot) {
            $gridTimes[] = $slot->startTime;
            $gridTimes[] = $slot->endTime;
        }
        $times = array_unique([
            ...$gridTimes,
            ...array_map(static fn (SaunaBooking $booking): string => $booking->startTime, $dayBookings),
            ...array_map(static fn (SaunaBooking $booking): string => $booking->endTime, $dayBookings),
            ...array_map(static fn (SaunaDuty $duty): string => $duty->time, $dayDuties),
        ]);
        usort($times, static fn (string $left, string $right): int => SaunaOpeningHours::toMinutes($left) <=> SaunaOpeningHours::toMinutes($right));

        $points = [];
        foreach ($times as $time) {
            $minute = SaunaOpeningHours::toMinutes($time);
            $starting = array_values(array_filter($dayBookings, static fn (SaunaBooking $booking): bool => $booking->startTime === $time));
            $running = array_values(array_filter(
                $dayBookings,
                static fn (SaunaBooking $booking): bool => SaunaOpeningHours::toMinutes($booking->startTime) < $minute
                    && $minute < SaunaOpeningHours::toMinutes($booking->endTime),
            ));
            $ending = array_values(array_filter($dayBookings, static fn (SaunaBooking $booking): bool => $booking->endTime === $time));
            $involved = [...$starting, ...$ending];
            $state = match (true) {
                array_any($involved, static fn (SaunaBooking $booking): bool => $booking->status === SaunaBookingStatus::Accepted) => SaunaDutyPointState::Required,
                $involved !== [] => SaunaDutyPointState::Requested,
                $running !== [] => SaunaDutyPointState::Running,
                in_array($time, $gridTimes, true) => SaunaDutyPointState::Free,
                default => SaunaDutyPointState::Unscheduled,
            };
            $kind = match (true) {
                $starting !== [] && $ending !== [] => SaunaDutyPointKind::Transition,
                $starting !== [] => SaunaDutyPointKind::Entry,
                $ending !== [] => SaunaDutyPointKind::Closing,
                default => SaunaDutyPointKind::None,
            };
            $waiver = $state === SaunaDutyPointState::Unscheduled
                ? null
                : array_find($dayWaivers, static fn (SaunaDutyWaiver $candidate): bool => $candidate->time === $time);
            $points[] = new SaunaDutyPoint(
                $time,
                $state,
                $kind,
                array_map(SaunaDutyBookingResponse::fromBooking(...), $starting),
                array_map(SaunaDutyBookingResponse::fromBooking(...), $ending),
                array_map(SaunaDutyBookingResponse::fromBooking(...), $running),
                array_values(array_map(
                    SaunaDutyResponse::fromDuty(...),
                    array_filter($dayDuties, static fn (SaunaDuty $duty): bool => $duty->time === $time),
                )),
                $waiver?->id,
                $state !== SaunaDutyPointState::Unscheduled && ($wholeDayWaived || $waiver !== null),
            );
        }

        return $points;
    }

    /** @param list<SaunaSeason> $seasons Vorbild: `SaunaSeasonManager::findCovering()`, hier ohne erneutes Laden je Tag. */
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
