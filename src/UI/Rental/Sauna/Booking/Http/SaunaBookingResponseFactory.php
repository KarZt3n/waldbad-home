<?php

namespace App\UI\Rental\Sauna\Booking\Http;

use App\Logic\Rental\Sauna\Booking\Dto\SaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaCalendarDay;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaCalendarResponse;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaCalendarSlot;
use App\UI\Rental\Sauna\Terms\Http\SaunaTermsResponseFactory;

readonly class SaunaBookingResponseFactory
{
    public function __construct(private SaunaTermsResponseFactory $termsResponseFactory)
    {
    }

    /** @return array<string, string|int|bool|array<string, string|null>|null> */
    public function booking(SaunaBookingResponse $booking): array
    {
        return [
            'id' => $booking->id,
            'date' => $booking->date->format('Y-m-d'),
            'startTime' => $booking->startTime,
            'endTime' => $booking->endTime,
            'personCount' => $booking->personCount,
            'priceCents' => $booking->priceCents,
            'firstName' => $booking->firstName,
            'lastName' => $booking->lastName,
            'birthDate' => $booking->birthDate->format('Y-m-d'),
            'email' => $booking->email,
            'message' => $booking->message,
            'status' => $booking->status->value,
            'memberId' => $booking->memberId,
            'memberNumber' => $booking->memberNumber,
            'submittedAt' => $booking->submittedAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $booking->updatedAt->format(\DateTimeInterface::ATOM),
            'individual' => $booking->individual,
            'memberContact' => $booking->memberContact === null ? null : [
                'memberNumber' => $booking->memberContact->memberNumber,
                'street' => $booking->memberContact->street,
                'postalCode' => $booking->memberContact->postalCode,
                'city' => $booking->memberContact->city,
                'email' => $booking->memberContact->email,
            ],
        ];
    }

    /**
     * @param list<SaunaBookingResponse> $bookings
     * @return array{items: list<array<string, string|int|bool|array<string, string|null>|null>>, total: int}
     */
    public function collection(array $bookings): array
    {
        return ['items' => array_map($this->booking(...), $bookings), 'total' => count($bookings)];
    }

    /**
     * Öffentliche Kalenderansicht: enthält bewusst keine Angaben dazu, wer einen Zeitraum belegt.
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     days: list<array{
     *         date: string,
     *         weekday: int,
     *         seasonName: string|null,
     *         slots: list<array{startTime: string, endTime: string, state: string}>
     *     }>,
     *     terms: array{priceCents: int, priceUnitMinutes: int, minPersons: int, maxPersons: int, updatedAt: string|null},
     *     season: array{name: string, startsOn: string}|null
     * }
     */
    public function calendar(SaunaCalendarResponse $calendar): array
    {
        return [
            'from' => $calendar->from->format('Y-m-d'),
            'to' => $calendar->to->format('Y-m-d'),
            'days' => array_map(
                static fn (SaunaCalendarDay $day): array => [
                    'date' => $day->date->format('Y-m-d'),
                    'weekday' => (int) $day->date->format('N'),
                    'seasonName' => $day->seasonName,
                    'slots' => array_map(
                        static fn (SaunaCalendarSlot $slot): array => [
                            'startTime' => $slot->startTime,
                            'endTime' => $slot->endTime,
                            'state' => $slot->state->value,
                        ],
                        $day->slots,
                    ),
                ],
                $calendar->days,
            ),
            'terms' => $this->termsResponseFactory->terms($calendar->terms),
            'season' => $calendar->seasonName === null || $calendar->seasonStartsOn === null ? null : [
                'name' => $calendar->seasonName,
                'startsOn' => $calendar->seasonStartsOn->format('Y-m-d'),
            ],
        ];
    }
}
