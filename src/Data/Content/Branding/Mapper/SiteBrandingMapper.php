<?php

namespace App\Data\Content\Branding\Mapper;

use App\Data\Content\Branding\Entity\SiteBrandingEntity;
use App\Logic\Content\Branding\Model\SiteBranding;

readonly class SiteBrandingMapper
{
    public function toModel(SiteBrandingEntity $entity): SiteBranding
    {
        return new SiteBranding($entity->getLogoUrl());
    }

    public function createEntity(SiteBranding $branding, \DateTimeImmutable $updatedAt): SiteBrandingEntity
    {
        return new SiteBrandingEntity(
            id: SiteBrandingEntity::ID,
            logoUrl: $branding->customLogoUrl,
            updatedAt: $updatedAt,
        );
    }

    public function updateEntity(SiteBranding $branding, SiteBrandingEntity $entity, \DateTimeImmutable $updatedAt): void
    {
        $entity->update($branding->customLogoUrl, $updatedAt);
    }
}
