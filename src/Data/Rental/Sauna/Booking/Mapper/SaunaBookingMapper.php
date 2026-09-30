<?php

namespace App\Data\Rental\Sauna\Booking\Mapper;

use App\Data\Rental\Sauna\Booking\Entity\SaunaBookingEntity;
use App\Data\Rental\Sauna\Booking\Entity\SaunaBookingParticipantEntity;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingParticipant;
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
            requestId: $entity->getRequestId(),
            participants: array_map(
                static fn (SaunaBookingParticipantEntity $participant): SaunaBookingParticipant => new SaunaBookingParticipant(
                    id: $participant->getId(),
                    firstName: $participant->getFirstName(),
                    lastName: $participant->getLastName(),
                ),
                $entity->getParticipants(),
            ),
        );
    }

    public function createEntity(SaunaBooking $booking): SaunaBookingEntity
    {
        $entity = new SaunaBookingEntity(
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
            requestId: $booking->requestId,
        );
        foreach ($booking->participants as $position => $participant) {
            $entity->addParticipant(new SaunaBookingParticipantEntity(
                id: $participant->id,
                booking: $entity,
                position: $position,
                firstName: $participant->firstName,
                lastName: $participant->lastName,
            ));
        }

        return $entity;
    }

    /** Nach dem Absenden ändert sich an einer Anmeldung fachlich nur noch der Status. */
    public function updateEntity(SaunaBooking $booking, SaunaBookingEntity $entity): void
    {
        $entity->changeStatus($booking->status->value, $booking->updatedAt);
    }
}
