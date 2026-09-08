<?php

namespace App\Data\Membership\ContributionRate\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'contribution_rate')]
#[ORM\UniqueConstraint(name: 'uniq_contribution_rate_category', columns: ['category'])]
class ContributionRateEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
        private ?string $category,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $label,
        #[ORM\Column(type: Types::INTEGER)]
        private int $amountCents,
        #[ORM\Column(type: Types::STRING, length: 20)]
        private string $period,
        #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
        private ?string $personGroup,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        private ?int $minAge,
        #[ORM\Column(type: Types::INTEGER, nullable: true)]
        private ?int $maxAge,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getCategory(): ?string { return $this->category; }
    public function getLabel(): string { return $this->label; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getPeriod(): string { return $this->period; }
    public function getPersonGroup(): ?string { return $this->personGroup; }
    public function getMinAge(): ?int { return $this->minAge; }
    public function getMaxAge(): ?int { return $this->maxAge; }

    public function update(string $label, int $amountCents, string $period, ?string $personGroup, ?int $minAge, ?int $maxAge): void
    {
        $this->label = $label;
        $this->amountCents = $amountCents;
        $this->period = $period;
        $this->personGroup = $personGroup;
        $this->minAge = $minAge;
        $this->maxAge = $maxAge;
    }
}
