<?php

namespace App\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitIndividualSaunaRequest;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitIndividualSaunaResponse;
use App\Logic\Rental\Sauna\Booking\Exception\SaunaRequestDayConflictException;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Mapping\SaunaBookingModelFactory;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;
use App\Logic\Rental\Sauna\Booking\Service\SaunaSlotAvailability;
use App\Logic\Rental\Sauna\Terms\Manager\SaunaTermsManagerInterface;

/**
 * Individuelle Anfrage mit frei gewählten Wunschtagen: Jeder Wunschtag wird zu einer eigenen
 * Buchung, die ihren Zeitraum im Kalender sofort belegt und vom Verein einzeln angenommen oder
 * abgelehnt wird. Alle Tage werden gemeinsam oder gar nicht angelegt; die Benachrichtigung nennt
 * alle Tage in einer Mail. Nicht anfragbare Tage werden gesammelt gemeldet, damit die Oberfläche
 * jeden betroffenen Wunschtag markieren kann.
 */
readonly class SubmitIndividualSaunaRequestUseCase
{
    public function __construct(
        private SaunaBookingModelFactory $factory,
        private SaunaSlotAvailability $availability,
        private SaunaTermsManagerInterface $terms,
        private SaunaBookingManagerInterface $manager,
        private SaunaBookingNotifierInterface $notifier,
    ) {
    }

    public function execute(SubmitIndividualSaunaRequest $request): SubmitIndividualSaunaResponse
    {
        $terms = $this->terms->current();
        foreach ($request->days as $day) {
            $terms->assertGroupSize($day->personCount);
        }
        $bookings = $this->factory->createFromIndividualRequest($request, $terms);
        $problems = [];
        foreach ($bookings as $index => $booking) {
            foreach ($bookings as $otherIndex => $other) {
                if ($otherIndex !== $index && $booking->overlaps($other->date, $other->startTime, $other->endTime)) {
                    $problems[$index] = 'Die Wunschtage dürfen sich zeitlich nicht überschneiden.';
                }
            }
            if (isset($problems[$index])) {
                continue;
            }
            try {
                $terms->assertMinimumDuration($booking->durationMinutes());
                $this->availability->assertIndividuallyRequestable($booking->date, $booking->startTime, $booking->endTime, $booking->submittedAt);
            } catch (BusinessRuleViolationException $exception) {
                $problems[$index] = $exception->getMessage();
            }
        }
        if ($problems !== []) {
            throw new SaunaRequestDayConflictException($problems, count($bookings));
        }

        $saved = $this->manager->saveAll($bookings);
        if ($saved !== []) {
            $this->notifier->individualRequestSubmitted($saved);
        }

        return new SubmitIndividualSaunaResponse(
            requestId: $bookings[0]->requestId ?? throw new \LogicException('Individuelle Anfragen haben immer eine Anfrage-Kennung.'),
            bookingIds: array_map(static fn (SaunaBooking $booking): string => $booking->id, $saved),
        );
    }
}
