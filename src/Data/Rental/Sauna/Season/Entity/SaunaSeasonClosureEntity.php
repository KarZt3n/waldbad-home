<?php

namespace App\Data\Rental\Sauna\Season\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sauna_season_closure')]
#[ORM\Index(name: 'idx_sauna_season_closure_season_position', columns: ['season_id', 'position'])]
class SaunaSeasonClosureEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: SaunaSeasonEntity::class, inversedBy: 'closures')]
        #[ORM\JoinColumn(name: 'season_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private SaunaSeasonEntity $season,
        #[ORM\Column(type: Types::INTEGER)]
        private int $position,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $startsOn,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $endsOn,
        #[ORM\Column(type: Types::STRING, length: 120)]
        private string $reason,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getSeason(): SaunaSeasonEntity { return $this->season; }
    public function getPosition(): int { return $this->position; }
    public function getStartsOn(): \DateTimeImmutable { return $this->startsOn; }
    public function getEndsOn(): \DateTimeImmutable { return $this->endsOn; }
    public function getReason(): string { return $this->reason; }
}
