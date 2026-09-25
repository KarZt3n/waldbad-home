<?php

namespace App\Data\Rental\Sauna\Booking\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sauna_booking')]
#[ORM\Index(name: 'idx_sauna_booking_date_start', columns: ['booking_date', 'start_time'])]
#[ORM\Index(name: 'idx_sauna_booking_status', columns: ['status'])]
class SaunaBookingEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(name: 'booking_date', type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $date,
        #[ORM\Column(type: Types::STRING, length: 5)]
        private string $startTime,
        #[ORM\Column(type: Types::STRING, length: 5)]
        private string $endTime,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $personCount,
        #[ORM\Column(type: Types::INTEGER)]
        private int $priceCents,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $firstName,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $lastName,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $birthDate,
        #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
        private ?string $email,
        #[ORM\Column(type: Types::TEXT)]
        private string $message,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $status,
        /** Verweis auf `MemberEntity::$id`, ohne DB-Fremdschlüssel (siehe `SaunaBooking::$memberId`). */
        #[ORM\Column(type: Types::STRING, length: 36, nullable: true)]
        private ?string $memberId,
        #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
        private ?string $memberNumber,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $submittedAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $individual = false,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getStartTime(): string { return $this->startTime; }
    public function getEndTime(): string { return $this->endTime; }
    public function getPersonCount(): int { return $this->personCount; }
    public function getPriceCents(): int { return $this->priceCents; }
    public function getFirstName(): string { return $this->firstName; }
    public function getLastName(): string { return $this->lastName; }
    public function getBirthDate(): \DateTimeImmutable { return $this->birthDate; }
    public function getEmail(): ?string { return $this->email; }
    public function getMessage(): string { return $this->message; }
    public function getStatus(): string { return $this->status; }
    public function getMemberId(): ?string { return $this->memberId; }
    public function getMemberNumber(): ?string { return $this->memberNumber; }
    public function getSubmittedAt(): \DateTimeImmutable { return $this->submittedAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function isIndividual(): bool { return $this->individual; }

    public function changeStatus(string $status, \DateTimeImmutable $updatedAt): void
    {
        $this->status = $status;
        $this->updatedAt = $updatedAt;
    }
}
