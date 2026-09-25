<?php

namespace App\Logic\Rental\Sauna\Booking\Dto;

use App\Logic\Rental\Sauna\Booking\Model\SaunaBooking;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingStatus;
use App\Logic\Rental\Sauna\Booking\Model\SaunaGuestContact;

readonly class SaunaBookingResponse
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $date,
        public string $startTime,
        public string $endTime,
        public int $personCount,
        public int $priceCents,
        public string $firstName,
        public string $lastName,
        public \DateTimeImmutable $birthDate,
        public ?string $email,
        public string $message,
        public SaunaBookingStatus $status,
        public ?string $memberId,
        public ?string $memberNumber,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
        public bool $individual,
        /** Aktuelle Kontaktdaten des verknüpften Mitglieds; nur in der Verwaltungsliste befüllt. */
        public ?SaunaGuestContact $memberContact = null,
    ) {
    }

    public static function fromBooking(SaunaBooking $booking, ?SaunaGuestContact $memberContact = null): self
    {
        return new self(
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
            status: $booking->status,
            memberId: $booking->memberId,
            memberNumber: $booking->memberNumber,
            submittedAt: $booking->submittedAt,
            updatedAt: $booking->updatedAt,
            individual: $booking->individual,
            memberContact: $memberContact,
        );
    }
}
