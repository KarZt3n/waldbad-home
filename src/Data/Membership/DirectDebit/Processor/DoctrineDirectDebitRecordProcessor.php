<?php

namespace App\Data\Membership\DirectDebit\Processor;

use App\Data\Membership\DirectDebit\Mapper\DirectDebitRecordMapper;
use App\Logic\Membership\DirectDebit\DirectDebitRecordProcessorInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;
use App\Data\Membership\DirectDebit\Entity\DirectDebitRecordEntity;
use Doctrine\ORM\EntityManagerInterface;

/** Historie-Einträge werden nur angelegt, nie inhaltlich geändert — nur bei einer Zahler-Übertragung umgeschrieben. */
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

    public function reassignPayer(string $fromPayerMemberId, string $toPayerMemberId): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(DirectDebitRecordEntity::class, 'record')
            ->set('record.payerMemberId', ':to')
            ->where('record.payerMemberId = :from')
            ->setParameter('to', $toPayerMemberId)
            ->setParameter('from', $fromPayerMemberId)
            ->getQuery()
            ->execute();
    }
}
