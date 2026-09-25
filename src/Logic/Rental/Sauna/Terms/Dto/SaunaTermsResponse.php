<?php

namespace App\Logic\Rental\Sauna\Terms\Dto;

use App\Logic\Rental\Sauna\Terms\Model\SaunaTerms;

readonly class SaunaTermsResponse
{
    public function __construct(
        public int $priceCents,
        public int $priceUnitMinutes,
        public int $minPersons,
        public int $maxPersons,
        public ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromTerms(SaunaTerms $terms): self
    {
        return new self(
            priceCents: $terms->priceCents,
            priceUnitMinutes: $terms->priceUnitMinutes,
            minPersons: $terms->minPersons,
            maxPersons: $terms->maxPersons,
            updatedAt: $terms->updatedAt,
        );
    }
}
