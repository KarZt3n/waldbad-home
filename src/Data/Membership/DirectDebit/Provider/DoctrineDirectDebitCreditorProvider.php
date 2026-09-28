<?php

namespace App\Data\Membership\DirectDebit\Provider;

use App\Data\Membership\DirectDebit\Entity\DirectDebitCreditorEntity;
use App\Data\Membership\DirectDebit\Mapper\DirectDebitCreditorMapper;
use App\Logic\Membership\DirectDebit\DirectDebitCreditorProviderInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineDirectDebitCreditorProvider implements DirectDebitCreditorProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DirectDebitCreditorMapper $mapper,
    ) {
    }

    public function get(): DirectDebitCreditor
    {
        $entity = $this->entityManager->find(DirectDebitCreditorEntity::class, DirectDebitCreditorEntity::ID);

        return $entity === null ? DirectDebitCreditor::empty() : $this->mapper->toModel($entity);
    }
}
