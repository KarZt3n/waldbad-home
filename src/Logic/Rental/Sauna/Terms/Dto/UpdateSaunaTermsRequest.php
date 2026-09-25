<?php

namespace App\Logic\Rental\Sauna\Terms\Dto;

readonly class UpdateSaunaTermsRequest
{
    public function __construct(
        public int $priceCents,
        public int $priceUnitMinutes,
        public int $minPersons,
        public int $maxPersons,
    ) {
    }
}
