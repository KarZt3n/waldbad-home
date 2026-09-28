<?php

namespace App\Data\Membership\DirectDebit\Provider;

use App\Data\Membership\DirectDebit\Entity\DirectDebitRecordEntity;
use App\Data\Membership\DirectDebit\Mapper\DirectDebitRecordMapper;
use App\Logic\Membership\DirectDebit\DirectDebitRecordProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineDirectDebitRecordProvider implements DirectDebitRecordProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DirectDebitRecordMapper $mapper,
    ) {
    }

    public function findByPayerMemberId(string $payerMemberId): array
    {
        $entities = $this->entityManager->getRepository(DirectDebitRecordEntity::class)
            ->findBy(['payerMemberId' => $payerMemberId], ['exportedAt' => 'DESC']);

        return array_map($this->mapper->toModel(...), $entities);
    }
}
