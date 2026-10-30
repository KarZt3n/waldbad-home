<?php

namespace App\Data\Content\SocialMedia\Provider;

use App\Data\Content\SocialMedia\Entity\SocialMediaSettingsEntity;
use App\Data\Content\SocialMedia\Mapper\SocialMediaSettingsMapper;
use App\Logic\Content\SocialMedia\Model\SocialMediaSettings;
use App\Logic\Content\SocialMedia\SocialMediaSettingsProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineSocialMediaSettingsProvider implements SocialMediaSettingsProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SocialMediaSettingsMapper $mapper,
    ) {
    }

    public function get(): SocialMediaSettings
    {
        $entity = $this->entityManager->find(SocialMediaSettingsEntity::class, SocialMediaSettingsEntity::ID);

        return $entity === null ? new SocialMediaSettings(visible: false, links: []) : $this->mapper->toModel($entity);
    }
}
