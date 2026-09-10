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
        /**
         * Geplante künftige Version des kompletten Beitragssatzes (siehe
         * `PendingContributionRateChange`) — flache, einzeln nullable Spalten statt einer
         * eingebetteten Struktur, wie der Rest dieses Projekts es durchgängig hält. Immer alle
         * zusammen gesetzt oder alle `null` (siehe `ContributionRateMapper`).
         */
        #[ORM\Column(name: 'pending_label', type: Types::STRING, length: 180, nullable: true)]
        private ?string $pendingLabel,
        #[ORM\Column(name: 'pending_amount_cents', type: Types::INTEGER, nullable: true)]
        private ?int $pendingAmountCents,
        #[ORM\Column(name: 'pending_period', type: Types::STRING, length: 20, nullable: true)]
        private ?string $pendingPeriod,
        #[ORM\Column(name: 'pending_person_group', type: Types::STRING, length: 20, nullable: true)]
        private ?string $pendingPersonGroup,
        #[ORM\Column(name: 'pending_min_age', type: Types::INTEGER, nullable: true)]
        private ?int $pendingMinAge,
        #[ORM\Column(name: 'pending_max_age', type: Types::INTEGER, nullable: true)]
        private ?int $pendingMaxAge,
        #[ORM\Column(name: 'pending_valid_from', type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $pendingValidFrom,
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
    public function getPendingLabel(): ?string { return $this->pendingLabel; }
    public function getPendingAmountCents(): ?int { return $this->pendingAmountCents; }
    public function getPendingPeriod(): ?string { return $this->pendingPeriod; }
    public function getPendingPersonGroup(): ?string { return $this->pendingPersonGroup; }
    public function getPendingMinAge(): ?int { return $this->pendingMinAge; }
    public function getPendingMaxAge(): ?int { return $this->pendingMaxAge; }
    public function getPendingValidFrom(): ?\DateTimeImmutable { return $this->pendingValidFrom; }

    public function update(
        string $label,
        int $amountCents,
        string $period,
        ?string $personGroup,
        ?int $minAge,
        ?int $maxAge,
        ?string $pendingLabel,
        ?int $pendingAmountCents,
        ?string $pendingPeriod,
        ?string $pendingPersonGroup,
        ?int $pendingMinAge,
        ?int $pendingMaxAge,
        ?\DateTimeImmutable $pendingValidFrom,
    ): void {
        $this->label = $label;
        $this->amountCents = $amountCents;
        $this->period = $period;
        $this->personGroup = $personGroup;
        $this->minAge = $minAge;
        $this->maxAge = $maxAge;
        $this->pendingLabel = $pendingLabel;
        $this->pendingAmountCents = $pendingAmountCents;
        $this->pendingPeriod = $pendingPeriod;
        $this->pendingPersonGroup = $pendingPersonGroup;
        $this->pendingMinAge = $pendingMinAge;
        $this->pendingMaxAge = $pendingMaxAge;
        $this->pendingValidFrom = $pendingValidFrom;
    }
}
