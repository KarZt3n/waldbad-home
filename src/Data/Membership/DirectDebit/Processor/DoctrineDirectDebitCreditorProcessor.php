<?php

namespace App\Data\Membership\DirectDebit\Processor;

use App\Data\Membership\DirectDebit\Entity\DirectDebitCreditorEntity;
use App\Data\Membership\DirectDebit\Mapper\DirectDebitCreditorMapper;
use App\Logic\Membership\DirectDebit\DirectDebitCreditorProcessorInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineDirectDebitCreditorProcessor implements DirectDebitCreditorProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DirectDebitCreditorMapper $mapper,
    ) {
    }

    public function save(DirectDebitCreditor $creditor): DirectDebitCreditor
    {
        $entity = $this->entityManager->find(DirectDebitCreditorEntity::class, DirectDebitCreditorEntity::ID);
        if ($entity === null) {
            $entity = $this->mapper->createEntity($creditor);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($creditor, $entity);
        }
        $this->entityManager->flush();

        return $this->mapper->toModel($entity);
    }
}
