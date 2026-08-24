<?php

namespace App\Data\Event\Activity\Processor;

use App\Data\Event\Activity\Entity\EventActivityEntity;
use App\Data\Event\Activity\Mapper\EventActivityMapper;
use App\Logic\Event\Activity\EventActivityProcessorInterface;
use App\Logic\Event\Activity\Model\EventActivity;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineEventActivityProcessor implements EventActivityProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventActivityMapper $mapper,
    ) {
    }

    public function save(EventActivity $activity): EventActivity
    {
        $entity = $this->entityManager->find(EventActivityEntity::class, $activity->id);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($activity);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($activity, $entity);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
