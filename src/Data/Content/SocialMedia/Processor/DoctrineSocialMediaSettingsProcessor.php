<?php

namespace App\Data\Content\SocialMedia\Processor;

use App\Data\Content\SocialMedia\Entity\SocialMediaSettingsEntity;
use App\Data\Content\SocialMedia\Mapper\SocialMediaSettingsMapper;
use App\Logic\Common\ClockInterface;
use App\Logic\Content\SocialMedia\Model\SocialMediaSettings;
use App\Logic\Content\SocialMedia\SocialMediaSettingsProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineSocialMediaSettingsProcessor implements SocialMediaSettingsProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SocialMediaSettingsMapper $mapper,
        private ClockInterface $clock,
    ) {
    }

    public function save(SocialMediaSettings $settings): SocialMediaSettings
    {
        $entity = $this->entityManager->find(SocialMediaSettingsEntity::class, SocialMediaSettingsEntity::ID);
        $now = $this->clock->now();
        if ($entity === null) {
            $entity = $this->mapper->createEntity($settings, $now);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($settings, $entity, $now);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
