<?php

namespace App\Data\Rental\Sauna\Booking\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sauna_booking_participant')]
#[ORM\Index(name: 'idx_sauna_booking_participant_booking_position', columns: ['booking_id', 'position'])]
class SaunaBookingParticipantEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: SaunaBookingEntity::class, inversedBy: 'participants')]
        #[ORM\JoinColumn(name: 'booking_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private SaunaBookingEntity $booking,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $position,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $firstName,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $lastName,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getPosition(): int { return $this->position; }
    public function getFirstName(): string { return $this->firstName; }
    public function getLastName(): string { return $this->lastName; }
}
