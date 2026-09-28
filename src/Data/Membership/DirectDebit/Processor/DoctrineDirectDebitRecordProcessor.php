<?php

namespace App\Data\Membership\DirectDebit\Processor;

use App\Data\Membership\DirectDebit\Mapper\DirectDebitRecordMapper;
use App\Logic\Membership\DirectDebit\DirectDebitRecordProcessorInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;
use Doctrine\ORM\EntityManagerInterface;

/** Historie-Einträge werden nur angelegt, nie geändert. */
readonly class DoctrineDirectDebitRecordProcessor implements DirectDebitRecordProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DirectDebitRecordMapper $mapper,
    ) {
    }

    public function save(DirectDebitRecord $record): DirectDebitRecord
    {
        $entity = $this->mapper->createEntity($record);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
