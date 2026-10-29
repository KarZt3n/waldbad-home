<?php

namespace App\Data\Rental\Sauna\Duty\Processor;

use App\Data\Rental\Sauna\Duty\Entity\SaunaDutyWaiverEntity;
use App\Data\Rental\Sauna\Duty\Mapper\SaunaDutyWaiverMapper;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyWaiver;
use App\Logic\Rental\Sauna\Duty\SaunaDutyWaiverProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

/** Vermerke werden nur angelegt und entfernt, nie geändert. */
readonly class DoctrineSaunaDutyWaiverProcessor implements SaunaDutyWaiverProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaunaDutyWaiverMapper $mapper,
    ) {
    }

    public function save(SaunaDutyWaiver $waiver): SaunaDutyWaiver
    {
        $entity = $this->entityManager->find(SaunaDutyWaiverEntity::class, $waiver->id);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($waiver);
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
        }

        return $this->mapper->toModel($entity);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(SaunaDutyWaiverEntity::class, $id);
        if ($entity !== null) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
