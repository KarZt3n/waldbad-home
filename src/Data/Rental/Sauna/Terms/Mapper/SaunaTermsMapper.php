<?php

namespace App\Data\Rental\Sauna\Terms\Mapper;

use App\Data\Rental\Sauna\Terms\Entity\SaunaTermsEntity;
use App\Logic\Rental\Sauna\Terms\Model\SaunaTerms;

readonly class SaunaTermsMapper
{
    public function toModel(SaunaTermsEntity $entity): SaunaTerms
    {
        return new SaunaTerms(
            priceCents: $entity->getPriceCents(),
            priceUnitMinutes: $entity->getPriceUnitMinutes(),
            minPersons: $entity->getMinPersons(),
            maxPersons: $entity->getMaxPersons(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }

    public function createEntity(SaunaTerms $terms): SaunaTermsEntity
    {
        return new SaunaTermsEntity(
            id: SaunaTermsEntity::SINGLETON_ID,
            priceCents: $terms->priceCents,
            priceUnitMinutes: $terms->priceUnitMinutes,
            minPersons: $terms->minPersons,
            maxPersons: $terms->maxPersons,
            updatedAt: $this->updatedAt($terms),
        );
    }

    public function updateEntity(SaunaTerms $terms, SaunaTermsEntity $entity): void
    {
        $entity->update(
            priceCents: $terms->priceCents,
            priceUnitMinutes: $terms->priceUnitMinutes,
            minPersons: $terms->minPersons,
            maxPersons: $terms->maxPersons,
            updatedAt: $this->updatedAt($terms),
        );
    }

    private function updatedAt(SaunaTerms $terms): \DateTimeImmutable
    {
        return $terms->updatedAt ?? throw new \LogicException('Gespeicherte Sauna-Konditionen benötigen einen Änderungszeitpunkt.');
    }
}
