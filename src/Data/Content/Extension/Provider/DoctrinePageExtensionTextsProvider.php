<?php

namespace App\Data\Content\Extension\Provider;

use App\Data\Content\Extension\Entity\PageExtensionTextsEntity;
use App\Data\Content\Extension\Mapper\PageExtensionTextsMapper;
use App\Logic\Content\Extension\Model\PageExtension;
use App\Logic\Content\Extension\Model\PageExtensionTexts;
use App\Logic\Content\Extension\PageExtensionTextsProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrinePageExtensionTextsProvider implements PageExtensionTextsProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PageExtensionTextsMapper $mapper,
    ) {
    }

    public function find(PageExtension $extension): ?PageExtensionTexts
    {
        $entity = $this->entityManager->find(PageExtensionTextsEntity::class, $extension->value);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }
}
