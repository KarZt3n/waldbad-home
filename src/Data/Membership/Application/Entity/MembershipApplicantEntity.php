<?php

namespace App\Data\Membership\Application\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'membership_applicant')]
#[ORM\Index(name: 'idx_membership_applicant_application_position', columns: ['application_id', 'position'])]
class MembershipApplicantEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: MembershipApplicationEntity::class, inversedBy: 'applicants')]
        #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private MembershipApplicationEntity $application,
        #[ORM\Column(type: Types::INTEGER)]
        private int $position,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $firstName,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $lastName,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $birthDate,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $street,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $houseNumber,
        #[ORM\Column(type: Types::STRING, length: 5)]
        private string $postalCode,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $city,
        #[ORM\Column(type: Types::STRING, length: 60, nullable: true)]
        private ?string $phone,
        #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
        private ?string $email,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getPosition(): int { return $this->position; }
    public function getFirstName(): string { return $this->firstName; }
    public function getLastName(): string { return $this->lastName; }
    public function getBirthDate(): \DateTimeImmutable { return $this->birthDate; }
    public function getStreet(): string { return $this->street; }
    public function getHouseNumber(): string { return $this->houseNumber; }
    public function getPostalCode(): string { return $this->postalCode; }
    public function getCity(): string { return $this->city; }
    public function getPhone(): ?string { return $this->phone; }
    public function getEmail(): ?string { return $this->email; }
}
