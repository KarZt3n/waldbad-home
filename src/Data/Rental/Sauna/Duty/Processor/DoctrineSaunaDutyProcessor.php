<?php

namespace App\Data\Rental\Sauna\Duty\Processor;

use App\Data\Rental\Sauna\Duty\Entity\SaunaDutyEntity;
use App\Data\Rental\Sauna\Duty\Mapper\SaunaDutyMapper;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDuty;
use App\Logic\Rental\Sauna\Duty\SaunaDutyProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

/** Dienste werden nur angelegt und entfernt, nie geändert. */
readonly class DoctrineSaunaDutyProcessor implements SaunaDutyProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaunaDutyMapper $mapper,
    ) {
    }

    public function save(SaunaDuty $duty): SaunaDuty
    {
        $entity = $this->entityManager->find(SaunaDutyEntity::class, $duty->id);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($duty);
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
        }

        return $this->mapper->toModel($entity);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(SaunaDutyEntity::class, $id);
        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
