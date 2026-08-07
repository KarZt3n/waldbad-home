<?php

namespace App\Data\Content\Page\Provider;

use App\Data\Content\Page\Entity\PageEntity;
use App\Data\Content\Page\Entity\PublishedPageEntity;
use App\Data\Content\Page\Mapper\PageMapper;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\PageProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrinePageProvider implements PageProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PageMapper $mapper,
    ) {
    }

    public function findById(string $id): ?Page
    {
        $entity = $this->entityManager->find(PageEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findPublishedBySlug(string $slug): ?Page
    {
        $entity = $this->entityManager->getRepository(PublishedPageEntity::class)->findOneBy([
            'slug' => $slug,
            'visible' => true,
        ]);

        return $entity === null ? null : $this->mapper->toPublishedModel($entity);
    }

    public function findPublishedById(string $id): ?Page
    {
        $page = $this->entityManager->getReference(PageEntity::class, $id);
        $entity = $this->entityManager->getRepository(PublishedPageEntity::class)->findOneBy([
            'page' => $page,
            'visible' => true,
        ]);

        return $entity === null ? null : $this->mapper->toPublishedModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(PageEntity::class)->findBy([], ['updatedAt' => 'DESC']);

        return array_map($this->mapper->toModel(...), $entities);
    }

    public function findPublishedNavigation(): array
    {
        $entities = $this->entityManager->getRepository(PublishedPageEntity::class)->findBy(
            ['visible' => true, 'showInNavigation' => true],
            ['navigationPosition' => 'ASC'],
        );

        return array_map($this->mapper->toPublishedModel(...), $entities);
    }

    public function findAllPublished(): array
    {
        $entities = $this->entityManager->getRepository(PublishedPageEntity::class)->findBy([], ['publishedAt' => 'DESC']);

        return array_map($this->mapper->toPublishedModel(...), $entities);
    }

    public function slugExists(string $slug, ?string $exceptId = null): bool
    {
        $entity = $this->entityManager->getRepository(PageEntity::class)->findOneBy(['slug' => $slug]);
        if ($entity !== null && $entity->getId() !== $exceptId) {
            return true;
        }

        $publication = $this->entityManager->getRepository(PublishedPageEntity::class)->findOneBy(['slug' => $slug]);

        return $publication !== null && $publication->getPage()->getId() !== $exceptId;
    }
}
