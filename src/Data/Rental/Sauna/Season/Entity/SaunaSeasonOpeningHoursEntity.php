<?php

namespace App\Data\Rental\Sauna\Season\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sauna_season_opening_hours')]
#[ORM\Index(name: 'idx_sauna_season_opening_hours_season_position', columns: ['season_id', 'position'])]
class SaunaSeasonOpeningHoursEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\ManyToOne(targetEntity: SaunaSeasonEntity::class, inversedBy: 'openingHours')]
        #[ORM\JoinColumn(name: 'season_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        private SaunaSeasonEntity $season,
        #[ORM\Column(type: Types::INTEGER)]
        private int $position,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $weekday,
        #[ORM\Column(type: Types::STRING, length: 5)]
        private string $startTime,
        #[ORM\Column(type: Types::STRING, length: 5)]
        private string $endTime,
    ) {
    }

    public function getId(): string { return $this->id; }
    public function getSeason(): SaunaSeasonEntity { return $this->season; }
    public function getPosition(): int { return $this->position; }
    public function getWeekday(): int { return $this->weekday; }
    public function getStartTime(): string { return $this->startTime; }
    public function getEndTime(): string { return $this->endTime; }
}
