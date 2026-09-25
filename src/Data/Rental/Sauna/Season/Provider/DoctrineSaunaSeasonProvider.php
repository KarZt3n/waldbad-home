<?php

namespace App\Data\Rental\Sauna\Season\Provider;

use App\Data\Rental\Sauna\Season\Entity\SaunaSeasonEntity;
use App\Data\Rental\Sauna\Season\Mapper\SaunaSeasonMapper;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\SaunaSeasonProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineSaunaSeasonProvider implements SaunaSeasonProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaunaSeasonMapper $mapper,
    ) {
    }

    public function find(string $id): ?SaunaSeason
    {
        $entity = $this->entityManager->find(SaunaSeasonEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(SaunaSeasonEntity::class)->findBy([], ['startsOn' => 'ASC']);

        return array_map($this->mapper->toModel(...), $entities);
    }
}
