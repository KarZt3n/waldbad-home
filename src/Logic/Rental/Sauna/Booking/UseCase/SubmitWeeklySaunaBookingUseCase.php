<?php

namespace App\Logic\Rental\Sauna\Booking\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaSkippedDate;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitWeeklySaunaBookingRequest;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitWeeklySaunaBookingResponse;
use App\Logic\Rental\Sauna\Booking\Manager\SaunaBookingManagerInterface;
use App\Logic\Rental\Sauna\Booking\Mapping\SaunaBookingModelFactory;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\SaunaBookingNotifierInterface;
use App\Logic\Rental\Sauna\Booking\Service\SaunaSlotAvailability;
use App\Logic\Rental\Sauna\Terms\Manager\SaunaTermsManagerInterface;

/**
 * Anfrage aus dem Kalender mit wöchentlicher Wiederholung: derselbe Wochentag zur selben Zeit bis
 * einschließlich `repeatUntil`, je Termin eine eigene Buchung, die der Verein einzeln oder gesammelt
 * annimmt. Der erste Termin muss frei sein; spätere Termine, die belegt, geschlossen oder außerhalb
 * der Saison sind, werden übersprungen und in der Antwort genannt, statt die ganze Serie abzulehnen.
 */
readonly class SubmitWeeklySaunaBookingUseCase
{
    /** Längste Serie, damit eine einzelne Anfrage nicht unbegrenzt in die Zukunft reserviert. */
    public const int MAX_WEEKS = 52;

    public function __construct(
        private SaunaBookingModelFactory $factory,
        private SaunaSlotAvailability $availability,
        private SaunaTermsManagerInterface $terms,
        private SaunaBookingManagerInterface $manager,
        private SaunaBookingNotifierInterface $notifier,
    ) {
    }

    public function execute(SubmitWeeklySaunaBookingRequest $request): SubmitWeeklySaunaBookingResponse
    {
        $booking = $request->firstBooking;
        $terms = $this->terms->current();
        $terms->assertGroupSize($booking->personCount);
        if (count($booking->participants) !== $booking->personCount) {
            throw new BusinessRuleViolationException('Für jede Person der Gruppe sind Vorname und Nachname anzugeben.');
        }

        $series = $this->factory->createWeeklyFromRequest($booking, $terms, $this->weeklyDates($booking->date->setTime(0, 0), $request->repeatUntil));
        $first = $series[0];
        $terms->assertBookingDuration($first->durationMinutes());
        $terms->assertLeadTime($first->date, $first->submittedAt);
        $this->availability->assertBookable($first->date, $first->startTime, $first->endTime, $first->submittedAt);

        $bookable = [];
        $skipped = [];
        foreach ($series as $index => $candidate) {
            if ($index > 0) {
                try {
                    $this->availability->assertBookable($candidate->date, $candidate->startTime, $candidate->endTime, $candidate->submittedAt);
                } catch (BusinessRuleViolationException $exception) {
                    $skipped[] = new SaunaSkippedDate($candidate->date, $exception->getMessage());
                    continue;
                }
            }
            $bookable[] = $candidate;
        }

        $saved = $this->manager->saveAll($bookable);
        if ($saved === []) {
            throw new \LogicException('Der bereits geprüfte erste Termin wurde nicht gespeichert.');
        }
        $this->notifier->weeklyBookingsSubmitted($saved);

        return new SubmitWeeklySaunaBookingResponse(
            requestId: $series[0]->requestId ?? throw new \LogicException('Wöchentliche Anfragen haben immer eine Anfrage-Kennung.'),
            bookedDates: array_map(static fn (SaunaBooking $saved): \DateTimeImmutable => $saved->date, $saved),
            skippedDates: $skipped,
        );
    }

    /**
     * @return non-empty-list<\DateTimeImmutable>
     */
    private function weeklyDates(\DateTimeImmutable $first, \DateTimeImmutable $repeatUntil): array
    {
        $until = $repeatUntil->setTime(0, 0);
        if ($until < $first->modify('+1 week')) {
            throw new BusinessRuleViolationException('Eine wöchentliche Wiederholung muss mindestens bis eine Woche nach dem ersten Termin reichen.');
        }
        if ($until > $first->modify(sprintf('+%d weeks', self::MAX_WEEKS))) {
            throw new BusinessRuleViolationException(sprintf('Eine wöchentliche Wiederholung ist für höchstens %d Wochen möglich.', self::MAX_WEEKS));
        }

        $dates = [$first];
        for ($date = $first->modify('+1 week'); $date <= $until; $date = $date->modify('+1 week')) {
            $dates[] = $date;
        }

        return $dates;
    }
}
