<?php

namespace App\Data\Membership\WorkAssignmentRefund\Processor;

use App\Data\Membership\WorkAssignmentRefund\Mapper\WorkAssignmentRefundRecordMapper;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;
use App\Logic\Membership\WorkAssignmentRefund\WorkAssignmentRefundRecordProcessorInterface;
use App\Data\Membership\WorkAssignmentRefund\Entity\WorkAssignmentRefundRecordEntity;
use Doctrine\ORM\EntityManagerInterface;

/** Historie-Einträge werden nur angelegt, nie inhaltlich geändert — nur bei einer Zahler-Übertragung umgeschrieben. */
readonly class DoctrineWorkAssignmentRefundRecordProcessor implements WorkAssignmentRefundRecordProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkAssignmentRefundRecordMapper $mapper,
    ) {
    }

    public function saveAll(array $records): void
    {
        foreach ($records as $record) {
            $this->entityManager->persist($this->mapper->createEntity($record));
        }
        $this->entityManager->flush();
    }

    public function reassignPayer(string $fromPayerMemberId, string $toPayerMemberId): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(WorkAssignmentRefundRecordEntity::class, 'record')
            ->set('record.payerMemberId', ':to')
            ->where('record.payerMemberId = :from')
            ->setParameter('to', $toPayerMemberId)
            ->setParameter('from', $fromPayerMemberId)
            ->getQuery()
            ->execute();
    }

    public function reassignHousehold(string $fromPrimaryMemberNumber, string $toPrimaryMemberNumber): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(WorkAssignmentRefundRecordEntity::class, 'record')
            ->set('record.primaryMemberNumber', ':to')
            ->where('record.primaryMemberNumber = :from')
            ->setParameter('to', $toPrimaryMemberNumber)
            ->setParameter('from', $fromPrimaryMemberNumber)
            ->getQuery()
            ->execute();
    }
}
