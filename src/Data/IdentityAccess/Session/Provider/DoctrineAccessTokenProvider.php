<?php

namespace App\Data\IdentityAccess\Session\Provider;

use App\Data\IdentityAccess\Session\Entity\AccessTokenEntity;
use App\Data\IdentityAccess\Session\Mapper\AccessTokenMapper;
use App\Logic\IdentityAccess\Session\AccessTokenProviderInterface;
use App\Logic\IdentityAccess\Session\Model\AccessToken;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineAccessTokenProvider implements AccessTokenProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccessTokenMapper $mapper,
    ) {
    }

    public function findByHash(string $tokenHash): ?AccessToken
    {
        $entity = $this->entityManager->getRepository(AccessTokenEntity::class)->findOneBy(['tokenHash' => $tokenHash]);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }
}
