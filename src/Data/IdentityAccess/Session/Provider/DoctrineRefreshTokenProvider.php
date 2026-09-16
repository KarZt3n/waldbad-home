<?php

namespace App\Data\IdentityAccess\Session\Provider;

use App\Data\IdentityAccess\Session\Entity\RefreshTokenEntity;
use App\Data\IdentityAccess\Session\Mapper\RefreshTokenMapper;
use App\Logic\IdentityAccess\Session\Model\RefreshToken;
use App\Logic\IdentityAccess\Session\RefreshTokenProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineRefreshTokenProvider implements RefreshTokenProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RefreshTokenMapper $mapper,
    ) {
    }

    public function findByHash(string $tokenHash): ?RefreshToken
    {
        $entity = $this->entityManager->getRepository(RefreshTokenEntity::class)->findOneBy(['tokenHash' => $tokenHash]);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }
}
