<?php

namespace App\Logic\Rental\Sauna\Booking\Mapping;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaParticipantInput;
use App\Logic\Rental\Sauna\Booking\Dto\SaunaRequestDayInput;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitIndividualSaunaRequest;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitSaunaBookingRequest;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingParticipant;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\SaunaGuestMatcherInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Terms\Model\SaunaTerms;

readonly class SaunaBookingModelFactory
{
    public function __construct(
        private SaunaGuestMatcherInterface $guestMatcher,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function createFromRequest(SubmitSaunaBookingRequest $request, SaunaTerms $terms): SaunaBooking
    {
        $now = $this->clock->now();
        $firstName = trim($request->firstName);
        $lastName = trim($request->lastName);
        $email = $this->email($request->email);
        $match = $this->guestMatcher->match($firstName, $lastName, $request->birthDate, $email);

        return new SaunaBooking(
            id: $this->identifierGenerator->generate(),
            date: $request->date->setTime(0, 0),
            startTime: $request->startTime,
            endTime: $request->endTime,
            personCount: $request->personCount,
            priceCents: $this->price($terms, $request->startTime, $request->endTime),
            firstName: $firstName,
            lastName: $lastName,
            birthDate: $request->birthDate,
            email: $email,
            message: trim($request->message),
            status: SaunaBookingStatus::Open,
            memberId: $match?->memberId,
            memberNumber: $match?->memberNumber,
            submittedAt: $now,
            updatedAt: $now,
            participants: $this->participants($request->participants),
        );
    }

    /**
     * Je Wunschtag eine eigene Buchung, alle mit derselben Anfrage-Kennung (`SaunaBooking::$requestId`).
     *
     * @return non-empty-list<SaunaBooking>
     */
    public function createFromIndividualRequest(SubmitIndividualSaunaRequest $request, SaunaTerms $terms): array
    {
        $now = $this->clock->now();
        $firstName = trim($request->firstName);
        $lastName = trim($request->lastName);
        $email = $this->email($request->email);
        $match = $this->guestMatcher->match($firstName, $lastName, $request->birthDate, $email);
        $requestId = $this->identifierGenerator->generate();

        return array_map(fn (SaunaRequestDayInput $day): SaunaBooking => new SaunaBooking(
            id: $this->identifierGenerator->generate(),
            date: $day->date->setTime(0, 0),
            startTime: $day->startTime,
            endTime: $day->endTime,
            personCount: $day->personCount,
            priceCents: $this->price($terms, $day->startTime, $day->endTime),
            firstName: $firstName,
            lastName: $lastName,
            birthDate: $request->birthDate,
            email: $email,
            message: trim($request->message),
            status: SaunaBookingStatus::Open,
            memberId: $match?->memberId,
            memberNumber: $match?->memberNumber,
            submittedAt: $now,
            updatedAt: $now,
            individual: true,
            requestId: $requestId,
            participants: $this->participants($day->participants),
        ), $request->days);
    }

    /**
     * @param list<SaunaParticipantInput> $participants
     *
     * @return list<SaunaBookingParticipant>
     */
    private function participants(array $participants): array
    {
        return array_map(fn (SaunaParticipantInput $participant): SaunaBookingParticipant => new SaunaBookingParticipant(
            id: $this->identifierGenerator->generate(),
            firstName: trim($participant->firstName),
            lastName: trim($participant->lastName),
        ), $participants);
    }

    private function email(?string $email): ?string
    {
        return $email !== null && trim($email) !== '' ? trim($email) : null;
    }

    private function price(SaunaTerms $terms, string $startTime, string $endTime): int
    {
        return $terms->priceFor(SaunaOpeningHours::toMinutes($endTime) - SaunaOpeningHours::toMinutes($startTime));
    }
}
