<?php

namespace App\Data\Membership\Cancellation\Provider;

use App\Data\Membership\Cancellation\Entity\MemberCancellationEntity;
use App\Data\Membership\Cancellation\Mapper\MemberCancellationMapper;
use App\Logic\Membership\Cancellation\MemberCancellationProviderInterface;
use App\Logic\Membership\Cancellation\Model\MemberCancellation;
use App\Logic\Membership\Cancellation\Model\MemberCancellationStatus;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineMemberCancellationProvider implements MemberCancellationProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MemberCancellationMapper $mapper,
    ) {
    }

    public function find(string $id): ?MemberCancellation
    {
        $entity = $this->entityManager->find(MemberCancellationEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(MemberCancellationEntity::class)->findBy([], ['submittedAt' => 'DESC']);

        return array_map($this->mapper->toModel(...), $entities);
    }

    public function findOpen(): array
    {
        $entities = $this->entityManager->getRepository(MemberCancellationEntity::class)
            ->findBy(['status' => MemberCancellationStatus::Open->value], ['submittedAt' => 'ASC']);

        return array_map($this->mapper->toModel(...), $entities);
    }

    public function countOpen(): int
    {
        return $this->entityManager->getRepository(MemberCancellationEntity::class)
            ->count(['status' => MemberCancellationStatus::Open->value]);
    }
}
