<?php

namespace App\Logic\Rental\Sauna\Season\Dto;

use App\Logic\Rental\Sauna\Season\Model\SaunaClosure;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;

readonly class CreateSaunaSeasonRequest
{
    /**
     * @param list<SaunaOpeningHours> $openingHours
     * @param list<SaunaClosure>      $closures
     */
    public function __construct(
        public \DateTimeImmutable $startsOn,
        public ?\DateTimeImmutable $endsOn,
        public int $slotDurationMinutes,
        public array $openingHours,
        public array $closures = [],
    ) {
    }
}
