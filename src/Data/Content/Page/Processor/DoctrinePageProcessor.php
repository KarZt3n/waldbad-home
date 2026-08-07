<?php

namespace App\Data\Content\Page\Processor;

use App\Data\Content\Page\Entity\PageEntity;
use App\Data\Content\Page\Entity\PublishedPageEntity;
use App\Data\Content\Page\Mapper\PageMapper;
use App\Logic\Common\Exception\ConcurrencyException;
use App\Logic\Content\Page\Exception\PageNotFoundException;
use App\Logic\Content\Page\Model\Page;
use App\Logic\Content\Page\Model\PageStatus;
use App\Logic\Content\Page\PageProcessorInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

readonly class DoctrinePageProcessor implements PageProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PageMapper $mapper,
    ) {
    }

    public function save(Page $page): Page
    {
        if ($page->version === 0) {
            $entity = $this->mapper->createEntity($page);
            $this->entityManager->persist($entity);
            $this->synchronizePublication($page, $entity);
            $this->entityManager->flush();

            return $this->mapper->toModel($entity);
        }

        $entity = $this->entityManager->find(PageEntity::class, $page->id);
        if ($entity === null) {
            throw new PageNotFoundException($page->id);
        }

        try {
            $this->entityManager->lock($entity, LockMode::OPTIMISTIC, $page->version);
            $this->mapper->updateEntity($page, $entity);
            $this->synchronizePublication($page, $entity);
            $this->entityManager->flush();
        } catch (OptimisticLockException $exception) {
            throw new ConcurrencyException(
                'Die Seite wurde zwischenzeitlich geändert. Bitte laden Sie die Daten neu.',
                previous: $exception,
            );
        }

        return $this->mapper->toModel($entity);
    }

    public function saveAll(array $pages): array
    {
        $entities = [];
        try {
            foreach ($pages as $page) {
                $entity = $this->entityManager->find(PageEntity::class, $page->id);
                if ($entity === null) {
                    throw new PageNotFoundException($page->id);
                }
                $this->entityManager->lock($entity, LockMode::OPTIMISTIC, $page->version);
                $this->mapper->updateEntity($page, $entity);
                $this->synchronizePublication($page, $entity);
                $entities[] = $entity;
            }
            $this->entityManager->flush();
        } catch (OptimisticLockException $exception) {
            throw new ConcurrencyException(
                'Die Seitenstruktur wurde zwischenzeitlich geändert. Bitte laden Sie die Daten neu.',
                previous: $exception,
            );
        }

        return array_map($this->mapper->toModel(...), $entities);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(PageEntity::class, $id);
        if ($entity === null) {
            throw new PageNotFoundException($id);
        }

        $publication = $this->entityManager->getRepository(PublishedPageEntity::class)->findOneBy(['page' => $entity]);
        if ($publication !== null) {
            $this->entityManager->remove($publication);
        }
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    private function synchronizePublication(Page $page, PageEntity $pageEntity): void
    {
        $publication = $page->version === 0
            ? null
            : $this->entityManager->getRepository(PublishedPageEntity::class)->findOneBy(['page' => $pageEntity]);

        if ($page->status === PageStatus::Published) {
            if ($publication === null) {
                $this->entityManager->persist($this->mapper->createPublishedEntity($pageEntity, $page));

                return;
            }

            $this->mapper->updatePublishedEntity($page, $publication);

            return;
        }

        if (($page->status === PageStatus::Archived || $page->publishedAt === null) && $publication !== null) {
            $this->entityManager->remove($publication);
        }
    }
}
