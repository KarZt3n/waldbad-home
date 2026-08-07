<?php

namespace App\Data\Guestbook\Entry\Provider;

use App\Data\Guestbook\Entry\Entity\GuestbookEntryEntity;
use App\Data\Guestbook\Entry\Mapper\GuestbookEntryMapper;
use App\Logic\Guestbook\Entry\GuestbookEntryProviderInterface;
use App\Logic\Guestbook\Entry\Model\GuestbookEntry;
use App\Logic\Guestbook\Entry\Model\GuestbookStatus;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineGuestbookEntryProvider implements GuestbookEntryProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GuestbookEntryMapper $mapper,
    ) {
    }

    public function find(string $id): ?GuestbookEntry
    {
        $entity = $this->entityManager->find(GuestbookEntryEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findPublished(int $limit, int $offset): array
    {
        $entities = $this->entityManager->getRepository(GuestbookEntryEntity::class)->findBy(
            ['status' => GuestbookStatus::Published->value],
            ['submittedAt' => 'DESC'],
            $limit,
            $offset,
        );

        return array_map($this->mapper->toModel(...), $entities);
    }

    public function countPublished(): int
    {
        return $this->entityManager->getRepository(GuestbookEntryEntity::class)->count([
            'status' => GuestbookStatus::Published->value,
        ]);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(GuestbookEntryEntity::class)->findBy([], ['submittedAt' => 'DESC']);

        return array_map($this->mapper->toModel(...), $entities);
    }
}
