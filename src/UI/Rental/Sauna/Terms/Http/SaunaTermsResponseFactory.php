<?php

namespace App\UI\Rental\Sauna\Terms\Http;

use App\Logic\Rental\Sauna\Terms\Dto\SaunaTermsResponse;

readonly class SaunaTermsResponseFactory
{
    /**
     * @return array{priceCents: int, priceUnitMinutes: int, minPersons: int, maxPersons: int, updatedAt: string|null}
     */
    public function terms(SaunaTermsResponse $terms): array
    {
        return [
            'priceCents' => $terms->priceCents,
            'priceUnitMinutes' => $terms->priceUnitMinutes,
            'minPersons' => $terms->minPersons,
            'maxPersons' => $terms->maxPersons,
            'updatedAt' => $terms->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
