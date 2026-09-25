<?php

namespace App\Data\Rental\Sauna\Season\Processor;

use App\Data\Rental\Sauna\Season\Entity\SaunaSeasonEntity;
use App\Data\Rental\Sauna\Season\Mapper\SaunaSeasonMapper;
use App\Logic\Rental\Sauna\Season\Exception\SaunaSeasonNotFoundException;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\SaunaSeasonProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineSaunaSeasonProcessor implements SaunaSeasonProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaunaSeasonMapper $mapper,
    ) {
    }

    public function save(SaunaSeason $season): SaunaSeason
    {
        $entity = $this->entityManager->find(SaunaSeasonEntity::class, $season->id);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($season);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($season, $entity);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(SaunaSeasonEntity::class, $id);
        if ($entity === null) {
            throw new SaunaSeasonNotFoundException($id);
        }
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}
