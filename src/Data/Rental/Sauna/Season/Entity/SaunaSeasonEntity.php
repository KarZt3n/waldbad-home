<?php

namespace App\Data\Rental\Sauna\Season\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sauna_season')]
#[ORM\Index(name: 'idx_sauna_season_starts_on', columns: ['starts_on'])]
class SaunaSeasonEntity
{
    /** @var Collection<int, SaunaSeasonOpeningHoursEntity> */
    #[ORM\OneToMany(targetEntity: SaunaSeasonOpeningHoursEntity::class, mappedBy: 'season', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $openingHours;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $name,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $startsOn,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $endsOn,
        #[ORM\Column(type: Types::INTEGER)]
        private int $slotDurationMinutes,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
        #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $closedOn = null,
    ) {
        $this->openingHours = new ArrayCollection();
    }

    public function getId(): string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getStartsOn(): \DateTimeImmutable { return $this->startsOn; }
    public function getEndsOn(): ?\DateTimeImmutable { return $this->endsOn; }
    public function getSlotDurationMinutes(): int { return $this->slotDurationMinutes; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getClosedOn(): ?\DateTimeImmutable { return $this->closedOn; }

    /** @return list<SaunaSeasonOpeningHoursEntity> */
    public function getOpeningHours(): array { return array_values($this->openingHours->toArray()); }

    public function update(
        string $name,
        \DateTimeImmutable $startsOn,
        ?\DateTimeImmutable $endsOn,
        int $slotDurationMinutes,
        \DateTimeImmutable $updatedAt,
        ?\DateTimeImmutable $closedOn,
    ): void {
        $this->name = $name;
        $this->startsOn = $startsOn;
        $this->endsOn = $endsOn;
        $this->slotDurationMinutes = $slotDurationMinutes;
        $this->updatedAt = $updatedAt;
        $this->closedOn = $closedOn;
    }

    /** @param list<SaunaSeasonOpeningHoursEntity> $openingHours */
    public function replaceOpeningHours(array $openingHours): void
    {
        $this->openingHours->clear();
        foreach ($openingHours as $hours) {
            $this->openingHours->add($hours);
        }
    }
}
