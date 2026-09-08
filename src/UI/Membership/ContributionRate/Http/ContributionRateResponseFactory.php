<?php

namespace App\UI\Membership\ContributionRate\Http;

use App\Logic\Membership\ContributionRate\Dto\ContributionRateResponse;

readonly class ContributionRateResponseFactory
{
    /**
     * @param list<ContributionRateResponse> $rates
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function collection(array $rates): array
    {
        return [
            'items' => array_map($this->rate(...), $rates),
            'total' => count($rates),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rate(ContributionRateResponse $rate): array
    {
        return [
            'id' => $rate->id,
            'category' => $rate->category?->value,
            'label' => $rate->label,
            'amountCents' => $rate->amountCents,
            'period' => $rate->period->value,
            'personGroup' => $rate->personGroup?->value,
            'minAge' => $rate->minAge,
            'maxAge' => $rate->maxAge,
            'annualAmountCents' => $rate->annualAmountCents,
        ];
    }
}
