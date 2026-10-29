<?php

namespace App\Data\Rental\Sauna\Booking\Processor;

use App\Data\Rental\Sauna\Booking\Entity\SaunaBookingApprovalTokenEntity;
use App\Data\Rental\Sauna\Booking\Mapper\SaunaBookingApprovalTokenMapper;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingApprovalToken;
use App\Logic\Rental\Sauna\Booking\SaunaBookingApprovalTokenProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

/** Nach dem Anlegen ändert sich an einem Token nur noch `usedAt`. */
readonly class DoctrineSaunaBookingApprovalTokenProcessor implements SaunaBookingApprovalTokenProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaunaBookingApprovalTokenMapper $mapper,
    ) {
    }

    public function save(SaunaBookingApprovalToken $token): void
    {
        $entity = $this->entityManager->find(SaunaBookingApprovalTokenEntity::class, $token->id);
        if ($entity === null) {
            $this->entityManager->persist($this->mapper->createEntity($token));
        } elseif ($token->usedAt !== null) {
            $entity->markUsed($token->usedAt);
        }
        $this->entityManager->flush();
    }
}
