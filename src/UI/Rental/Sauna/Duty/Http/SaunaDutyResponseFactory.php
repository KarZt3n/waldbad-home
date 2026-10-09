<?php

namespace App\UI\Rental\Sauna\Duty\Http;

use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyBookingResponse;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyPoint;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyResponse;
use App\Logic\Rental\Sauna\Duty\Dto\SaunaDutyRosterDay;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyCandidate;

readonly class SaunaDutyResponseFactory
{
    /**
     * @param list<SaunaDutyRosterDay> $days
     *
     * @return array{days: list<array{date: string, closed: bool, closedReason: string, points: list<array{time: string, state: string, kind: string, startingBookings: list<array<string, string|int>>, endingBookings: list<array<string, string|int>>, runningBookings: list<array<string, string|int>>, duties: list<array<string, string>>}>}>}
     */
    public function roster(array $days): array
    {
        return ['days' => array_map(static fn (SaunaDutyRosterDay $day): array => [
            'date' => $day->date->format('Y-m-d'),
            'closed' => $day->closed,
            'closedReason' => $day->closedReason,
            'points' => array_map(fn (SaunaDutyPoint $point): array => [
                'time' => $point->time,
                'state' => $point->state->value,
                'kind' => $point->kind->value,
                'startingBookings' => array_map(self::booking(...), $point->startingBookings),
                'endingBookings' => array_map(self::booking(...), $point->endingBookings),
                'runningBookings' => array_map(self::booking(...), $point->runningBookings),
                'duties' => array_map(self::duty(...), $point->duties),
            ], $day->points),
        ], $days)];
    }

    /** @return array<string, string> */
    public static function duty(SaunaDutyResponse $duty): array
    {
        return [
            'id' => $duty->id,
            'date' => $duty->date->format('Y-m-d'),
            'time' => $duty->time,
            'memberId' => $duty->memberId,
            'memberNumber' => $duty->memberNumber,
            'firstName' => $duty->firstName,
            'lastName' => $duty->lastName,
        ];
    }

    /**
     * @param list<SaunaDutyCandidate> $candidates
     *
     * @return array{items: list<array<string, string|bool|null>>}
     */
    public function candidates(array $candidates): array
    {
        return ['items' => array_map(static fn (SaunaDutyCandidate $candidate): array => [
            'id' => $candidate->memberId,
            'memberNumber' => $candidate->memberNumber,
            'firstName' => $candidate->firstName,
            'lastName' => $candidate->lastName,
            'street' => $candidate->street,
            'birthDate' => $candidate->birthDate?->format('Y-m-d'),
            'boardMember' => $candidate->boardMember,
        ], $candidates)];
    }

    /** @return array<string, string|int> */
    private static function booking(SaunaDutyBookingResponse $booking): array
    {
        return [
            'id' => $booking->id,
            'startTime' => $booking->startTime,
            'endTime' => $booking->endTime,
            'personCount' => $booking->personCount,
            'firstName' => $booking->firstName,
            'lastName' => $booking->lastName,
            'status' => $booking->status->value,
        ];
    }
}
