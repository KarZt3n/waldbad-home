<?php

namespace App\Data\Content\Footer\Mapper;

use App\Data\Content\Footer\Entity\FooterNavigationEntity;
use App\Logic\Content\Footer\Model\FooterNavigation;

readonly class FooterNavigationMapper
{
    public function toModel(FooterNavigationEntity $entity): FooterNavigation
    {
        return new FooterNavigation($entity->getPageIds());
    }

    public function createEntity(FooterNavigation $navigation, \DateTimeImmutable $updatedAt): FooterNavigationEntity
    {
        return new FooterNavigationEntity(
            id: FooterNavigationEntity::ID,
            pageIds: $navigation->pageIds,
            updatedAt: $updatedAt,
        );
    }

    public function updateEntity(FooterNavigation $navigation, FooterNavigationEntity $entity, \DateTimeImmutable $updatedAt): void
    {
        $entity->update($navigation->pageIds, $updatedAt);
    }
}
