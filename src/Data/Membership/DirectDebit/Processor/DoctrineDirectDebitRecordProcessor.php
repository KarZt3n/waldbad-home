<?php

namespace App\Data\Membership\DirectDebit\Processor;

use App\Data\Membership\DirectDebit\Mapper\DirectDebitRecordMapper;
use App\Logic\Membership\DirectDebit\DirectDebitRecordProcessorInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;
use App\Data\Membership\DirectDebit\Entity\DirectDebitCollectedChargeEntity;
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

    public function saveAll(array $records, array $collectedChargeIdsByRecordId = []): void
    {
        $exportedAtByRecordId = [];
        foreach ($records as $record) {
            $this->entityManager->persist($this->mapper->createEntity($record));
            $exportedAtByRecordId[$record->id] = $record->exportedAt;
        }
        foreach ($collectedChargeIdsByRecordId as $recordId => $chargeIds) {
            $collectedAt = $exportedAtByRecordId[$recordId] ?? throw new \LogicException('Einmalgebühren gehören zu einem Eintrag desselben Exports.');
            foreach (array_unique($chargeIds) as $chargeId) {
                $this->entityManager->persist(new DirectDebitCollectedChargeEntity($chargeId, $recordId, $collectedAt));
            }
        }
        $this->entityManager->flush();
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
