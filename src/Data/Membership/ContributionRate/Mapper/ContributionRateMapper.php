<?php

namespace App\Data\Membership\ContributionRate\Mapper;

use App\Data\Membership\ContributionRate\Entity\ContributionRateEntity;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use App\Logic\Membership\ContributionRate\Model\PendingContributionRateChange;
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
            pending: $this->toPendingModel($entity),
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
            pendingLabel: $rate->pending?->label,
            pendingAmountCents: $rate->pending?->amountCents,
            pendingPeriod: $rate->pending?->period->value,
            pendingPersonGroup: $rate->pending?->personGroup?->value,
            pendingMinAge: $rate->pending?->minAge,
            pendingMaxAge: $rate->pending?->maxAge,
            pendingValidFrom: $rate->pending?->validFrom,
        );
    }

    public function updateEntity(ContributionRate $rate, ContributionRateEntity $entity): void
    {
        $entity->update(
            $rate->label,
            $rate->amountCents,
            $rate->period->value,
            $rate->personGroup?->value,
            $rate->minAge,
            $rate->maxAge,
            $rate->pending?->label,
            $rate->pending?->amountCents,
            $rate->pending?->period->value,
            $rate->pending?->personGroup?->value,
            $rate->pending?->minAge,
            $rate->pending?->maxAge,
            $rate->pending?->validFrom,
        );
    }

    private function toPendingModel(ContributionRateEntity $entity): ?PendingContributionRateChange
    {
        if ($entity->getPendingValidFrom() === null) {
            return null;
        }

        return new PendingContributionRateChange(
            label: $entity->getPendingLabel() ?? '',
            amountCents: $entity->getPendingAmountCents() ?? 0,
            period: PaymentInterval::from($entity->getPendingPeriod() ?? PaymentInterval::Yearly->value),
            personGroup: $entity->getPendingPersonGroup() === null ? null : PersonGroup::from($entity->getPendingPersonGroup()),
            minAge: $entity->getPendingMinAge(),
            maxAge: $entity->getPendingMaxAge(),
            validFrom: $entity->getPendingValidFrom(),
        );
    }
}
