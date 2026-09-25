<?php

namespace App\Logic\Rental\Sauna\Booking\Mapping;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Rental\Sauna\Booking\Dto\SubmitSaunaBookingRequest;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
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
        $email = $request->email !== null && trim($request->email) !== '' ? trim($request->email) : null;
        $match = $this->guestMatcher->match($firstName, $lastName, $request->birthDate, $email);

        return new SaunaBooking(
            id: $this->identifierGenerator->generate(),
            date: $request->date->setTime(0, 0),
            startTime: $request->startTime,
            endTime: $request->endTime,
            personCount: $request->personCount,
            priceCents: $terms->priceFor(
                SaunaOpeningHours::toMinutes($request->endTime) - SaunaOpeningHours::toMinutes($request->startTime),
            ),
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
            individual: $request->individual,
        );
    }
}
