<?php

namespace App\Logic\Rental\Sauna\Season\Dto;

use App\Logic\Rental\Sauna\Season\Model\SaunaClosure;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;

readonly class SaunaSeasonResponse
{
    /**
     * @param list<SaunaOpeningHours> $openingHours
     * @param list<SaunaClosure>      $closures
     */
    public function __construct(
        public string $id,
        public \DateTimeImmutable $startsOn,
        public ?\DateTimeImmutable $endsOn,
        public int $slotDurationMinutes,
        public array $openingHours,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $closedOn,
        public array $closures = [],
    ) {
    }

    public static function fromSeason(SaunaSeason $season): self
    {
        return new self(
            id: $season->id,
            startsOn: $season->startsOn,
            endsOn: $season->endsOn,
            slotDurationMinutes: $season->slotDurationMinutes,
            openingHours: $season->openingHours,
            createdAt: $season->createdAt,
            updatedAt: $season->updatedAt,
            closedOn: $season->closedOn,
            closures: $season->closures,
        );
    }
}
