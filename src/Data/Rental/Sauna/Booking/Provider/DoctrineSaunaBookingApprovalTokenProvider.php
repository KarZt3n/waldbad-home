<?php

namespace App\Data\Rental\Sauna\Booking\Provider;

use App\Data\Rental\Sauna\Booking\Entity\SaunaBookingApprovalTokenEntity;
use App\Data\Rental\Sauna\Booking\Mapper\SaunaBookingApprovalTokenMapper;
use App\Logic\Rental\Sauna\Booking\Model\SaunaBookingApprovalToken;
use App\Logic\Rental\Sauna\Booking\SaunaBookingApprovalTokenProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineSaunaBookingApprovalTokenProvider implements SaunaBookingApprovalTokenProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaunaBookingApprovalTokenMapper $mapper,
    ) {
    }

    public function findByHash(string $tokenHash): ?SaunaBookingApprovalToken
    {
        $entity = $this->entityManager->getRepository(SaunaBookingApprovalTokenEntity::class)->findOneBy(['tokenHash' => $tokenHash]);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }
}
