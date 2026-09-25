<?php

namespace App\Logic\Rental\Sauna\Season\Dto;

use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;

readonly class SaunaSeasonResponse
{
    /**
     * @param list<SaunaOpeningHours> $openingHours
     */
    public function __construct(
        public string $id,
        public string $name,
        public \DateTimeImmutable $startsOn,
        public ?\DateTimeImmutable $endsOn,
        public int $slotDurationMinutes,
        public array $openingHours,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $closedOn,
    ) {
    }

    public static function fromSeason(SaunaSeason $season): self
    {
        return new self(
            id: $season->id,
            name: $season->name,
            startsOn: $season->startsOn,
            endsOn: $season->endsOn,
            slotDurationMinutes: $season->slotDurationMinutes,
            openingHours: $season->openingHours,
            createdAt: $season->createdAt,
            updatedAt: $season->updatedAt,
            closedOn: $season->closedOn,
        );
    }
}
