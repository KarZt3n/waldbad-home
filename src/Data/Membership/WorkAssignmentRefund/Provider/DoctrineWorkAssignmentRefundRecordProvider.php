<?php

namespace App\Data\Membership\WorkAssignmentRefund\Provider;

use App\Data\Membership\WorkAssignmentRefund\Entity\WorkAssignmentRefundRecordEntity;
use App\Data\Membership\WorkAssignmentRefund\Mapper\WorkAssignmentRefundRecordMapper;
use App\Logic\Membership\WorkAssignmentRefund\WorkAssignmentRefundRecordProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineWorkAssignmentRefundRecordProvider implements WorkAssignmentRefundRecordProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkAssignmentRefundRecordMapper $mapper,
    ) {
    }

    public function findByYear(int $year): array
    {
        $entities = $this->entityManager->getRepository(WorkAssignmentRefundRecordEntity::class)
            ->findBy(['year' => $year], ['exportedAt' => 'DESC']);

        return array_map($this->mapper->toModel(...), $entities);
    }
}
