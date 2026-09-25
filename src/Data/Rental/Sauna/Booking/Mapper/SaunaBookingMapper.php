<?php

namespace App\Data\Rental\Sauna\Booking\Mapper;

use App\Data\Rental\Sauna\Booking\Entity\SaunaBookingEntity;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;

readonly class SaunaBookingMapper
{
    public function toModel(SaunaBookingEntity $entity): SaunaBooking
    {
        return new SaunaBooking(
            id: $entity->getId(),
            date: $entity->getDate(),
            startTime: $entity->getStartTime(),
            endTime: $entity->getEndTime(),
            personCount: $entity->getPersonCount(),
            priceCents: $entity->getPriceCents(),
            firstName: $entity->getFirstName(),
            lastName: $entity->getLastName(),
            birthDate: $entity->getBirthDate(),
            email: $entity->getEmail(),
            message: $entity->getMessage(),
            status: SaunaBookingStatus::from($entity->getStatus()),
            memberId: $entity->getMemberId(),
            memberNumber: $entity->getMemberNumber(),
            submittedAt: $entity->getSubmittedAt(),
            updatedAt: $entity->getUpdatedAt(),
            individual: $entity->isIndividual(),
        );
    }

    public function createEntity(SaunaBooking $booking): SaunaBookingEntity
    {
        return new SaunaBookingEntity(
            id: $booking->id,
            date: $booking->date,
            startTime: $booking->startTime,
            endTime: $booking->endTime,
            personCount: $booking->personCount,
            priceCents: $booking->priceCents,
            firstName: $booking->firstName,
            lastName: $booking->lastName,
            birthDate: $booking->birthDate,
            email: $booking->email,
            message: $booking->message,
            status: $booking->status->value,
            memberId: $booking->memberId,
            memberNumber: $booking->memberNumber,
            submittedAt: $booking->submittedAt,
            updatedAt: $booking->updatedAt,
            individual: $booking->individual,
        );
    }

    /** Nach dem Absenden ändert sich an einer Anmeldung fachlich nur noch der Status. */
    public function updateEntity(SaunaBooking $booking, SaunaBookingEntity $entity): void
    {
        $entity->changeStatus($booking->status->value, $booking->updatedAt);
    }
}
