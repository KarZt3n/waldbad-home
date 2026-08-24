<?php

namespace App\Data\Event\Activity\Provider;

use App\Data\Event\Activity\Entity\EventActivityEntity;
use App\Data\Event\Activity\Mapper\EventActivityMapper;
use App\Logic\Event\Activity\EventActivityProviderInterface;
use App\Logic\Event\Activity\Model\EventActivity;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineEventActivityProvider implements EventActivityProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventActivityMapper $mapper,
    ) {
    }

    public function find(string $id): ?EventActivity
    {
        $entity = $this->entityManager->find(EventActivityEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(EventActivityEntity::class)->findBy([], ['name' => 'ASC']);

        return array_map($this->mapper->toModel(...), $entities);
    }
}
