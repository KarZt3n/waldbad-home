<?php

namespace App\Data\Membership\ContributionRate\Provider;

use App\Data\Membership\ContributionRate\Entity\ContributionRateEntity;
use App\Data\Membership\ContributionRate\Mapper\ContributionRateMapper;
use App\Logic\Membership\ContributionRate\ContributionRateProviderInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineContributionRateProvider implements ContributionRateProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContributionRateMapper $mapper,
    ) {
    }

    public function find(string $id): ?ContributionRate
    {
        $entity = $this->entityManager->find(ContributionRateEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findByCategory(ContributionCategory $category): ?ContributionRate
    {
        $entity = $this->entityManager->getRepository(ContributionRateEntity::class)
            ->findOneBy(['category' => $category->value]);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(ContributionRateEntity::class)->findBy([], ['category' => 'ASC']);

        return array_map($this->mapper->toModel(...), $entities);
    }
}
