<?php

namespace App\Data\Event\Schedule\Processor;

use App\Data\Event\Schedule\Entity\EventScheduleEntity;
use App\Data\Event\Schedule\Mapper\EventScheduleMapper;
use App\Logic\Event\Schedule\EventScheduleProcessorInterface;
use App\Logic\Event\Schedule\Exception\EventScheduleNotFoundException;
use App\Logic\Event\Schedule\Model\EventSchedule;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineEventScheduleProcessor implements EventScheduleProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventScheduleMapper $mapper,
    ) {
    }

    public function save(EventSchedule $schedule): EventSchedule
    {
        $entity = $this->entityManager->find(EventScheduleEntity::class, $schedule->id);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($schedule);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($schedule, $entity);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(EventScheduleEntity::class, $id);
        if ($entity === null) {
            throw new EventScheduleNotFoundException($id);
        }
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
