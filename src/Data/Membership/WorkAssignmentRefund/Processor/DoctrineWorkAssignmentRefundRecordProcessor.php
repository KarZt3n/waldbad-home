<?php

namespace App\Data\Membership\WorkAssignmentRefund\Processor;

use App\Data\Membership\WorkAssignmentRefund\Mapper\WorkAssignmentRefundRecordMapper;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundRecord;
use App\Logic\Membership\WorkAssignmentRefund\WorkAssignmentRefundRecordProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

/** Historie-Einträge werden nur angelegt, nie geändert. */
readonly class DoctrineWorkAssignmentRefundRecordProcessor implements WorkAssignmentRefundRecordProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkAssignmentRefundRecordMapper $mapper,
    ) {
    }

    public function save(WorkAssignmentRefundRecord $record): WorkAssignmentRefundRecord
    {
        $entity = $this->mapper->createEntity($record);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
