<?php

namespace App\Data\Event\Schedule\Provider;

use App\Data\Event\Schedule\Entity\EventScheduleEntity;
use App\Data\Event\Schedule\Mapper\EventScheduleMapper;
use App\Logic\Event\Schedule\EventScheduleProviderInterface;
use App\Logic\Event\Schedule\Model\EventSchedule;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineEventScheduleProvider implements EventScheduleProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventScheduleMapper $mapper,
    ) {
    }

    public function find(string $id): ?EventSchedule
    {
        $entity = $this->entityManager->find(EventScheduleEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(EventScheduleEntity::class)->findBy([], ['date' => 'ASC', 'time' => 'ASC']);

        return array_map($this->mapper->toModel(...), $entities);
    }
}
