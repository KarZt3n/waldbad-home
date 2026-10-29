<?php

namespace App\Data\Content\Extension\Mapper;

use App\Data\Content\Extension\Entity\PageExtensionTextsEntity;
use App\Logic\Content\Extension\Model\PageExtension;
use App\Logic\Content\Extension\Model\PageExtensionTexts;

readonly class PageExtensionTextsMapper
{
    public function toModel(PageExtensionTextsEntity $entity): ?PageExtensionTexts
    {
        $extension = PageExtension::tryFrom($entity->getExtensionKey());

        return $extension === null ? null : new PageExtensionTexts($extension, $entity->getTexts());
    }

    public function createEntity(PageExtensionTexts $texts, \DateTimeImmutable $updatedAt): PageExtensionTextsEntity
    {
        return new PageExtensionTextsEntity($texts->extension->value, $texts->overrides, $updatedAt);
    }

    public function updateEntity(PageExtensionTexts $texts, PageExtensionTextsEntity $entity, \DateTimeImmutable $updatedAt): void
    {
        $entity->update($texts->overrides, $updatedAt);
    }
}
