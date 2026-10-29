<?php

namespace App\Data\Content\Extension\Processor;

use App\Data\Content\Extension\Entity\PageExtensionTextsEntity;
use App\Data\Content\Extension\Mapper\PageExtensionTextsMapper;
use App\Logic\Common\ClockInterface;
use App\Logic\Content\Extension\Model\PageExtensionTexts;
use App\Logic\Content\Extension\PageExtensionTextsProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrinePageExtensionTextsProcessor implements PageExtensionTextsProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PageExtensionTextsMapper $mapper,
        private ClockInterface $clock,
    ) {
    }

    public function save(PageExtensionTexts $texts): PageExtensionTexts
    {
        $entity = $this->entityManager->find(PageExtensionTextsEntity::class, $texts->extension->value);
        $now = $this->clock->now();
        if ($entity === null) {
            $entity = $this->mapper->createEntity($texts, $now);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($texts, $entity, $now);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity) ?? $texts;
    }
}
