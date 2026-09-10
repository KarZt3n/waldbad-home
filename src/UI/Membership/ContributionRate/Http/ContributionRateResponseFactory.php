<?php

namespace App\UI\Membership\ContributionRate\Http;

use App\Logic\Membership\ContributionRate\Dto\ContributionRateResponse;
use App\Logic\Membership\ContributionRate\Model\PendingContributionRateChange;

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
            'status' => $rate->pending !== null ? 'pending' : 'active',
            'pending' => $rate->pending !== null ? $this->pending($rate->pending) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pending(PendingContributionRateChange $pending): array
    {
        return [
            'label' => $pending->label,
            'amountCents' => $pending->amountCents,
            'period' => $pending->period->value,
            'personGroup' => $pending->personGroup?->value,
            'minAge' => $pending->minAge,
            'maxAge' => $pending->maxAge,
            'validFrom' => $pending->validFrom->format('Y-m-d'),
        ];
    }
}
