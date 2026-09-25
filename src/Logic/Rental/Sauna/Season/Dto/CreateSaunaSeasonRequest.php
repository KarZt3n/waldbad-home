<?php

namespace App\Logic\Rental\Sauna\Season\Dto;

use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;

readonly class CreateSaunaSeasonRequest
{
    /**
     * @param list<SaunaOpeningHours> $openingHours
     */
    public function __construct(
        public string $name,
        public \DateTimeImmutable $startsOn,
        public ?\DateTimeImmutable $endsOn,
        public int $slotDurationMinutes,
        public array $openingHours,
    ) {
    }
}
