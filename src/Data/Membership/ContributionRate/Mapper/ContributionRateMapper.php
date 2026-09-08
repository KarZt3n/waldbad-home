<?php

namespace App\Data\Membership\ContributionRate\Mapper;

use App\Data\Membership\ContributionRate\Entity\ContributionRateEntity;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use App\Logic\Membership\ContributionRate\Model\PersonGroup;
use App\Logic\Membership\PaymentInterval;

readonly class ContributionRateMapper
{
    public function toModel(ContributionRateEntity $entity): ContributionRate
    {
        return new ContributionRate(
            id: $entity->getId(),
            category: $entity->getCategory() === null ? null : ContributionCategory::from($entity->getCategory()),
            label: $entity->getLabel(),
            amountCents: $entity->getAmountCents(),
            period: PaymentInterval::from($entity->getPeriod()),
            personGroup: $entity->getPersonGroup() === null ? null : PersonGroup::from($entity->getPersonGroup()),
            minAge: $entity->getMinAge(),
            maxAge: $entity->getMaxAge(),
        );
    }

    public function createEntity(ContributionRate $rate): ContributionRateEntity
    {
        return new ContributionRateEntity(
            id: $rate->id,
            category: $rate->category?->value,
            label: $rate->label,
            amountCents: $rate->amountCents,
            period: $rate->period->value,
            personGroup: $rate->personGroup?->value,
            minAge: $rate->minAge,
            maxAge: $rate->maxAge,
        );
    }

    public function updateEntity(ContributionRate $rate, ContributionRateEntity $entity): void
    {
        $entity->update($rate->label, $rate->amountCents, $rate->period->value, $rate->personGroup?->value, $rate->minAge, $rate->maxAge);
    }
}
