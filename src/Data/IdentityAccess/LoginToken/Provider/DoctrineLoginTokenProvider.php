<?php

namespace App\Data\IdentityAccess\LoginToken\Provider;

use App\Data\IdentityAccess\LoginToken\Entity\LoginTokenEntity;
use App\Data\IdentityAccess\LoginToken\Mapper\LoginTokenMapper;
use App\Logic\IdentityAccess\LoginToken\LoginTokenProviderInterface;
use App\Logic\IdentityAccess\LoginToken\Model\LoginToken;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineLoginTokenProvider implements LoginTokenProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoginTokenMapper $mapper,
    ) {
    }

    public function findByHash(string $tokenHash): ?LoginToken
    {
        $entity = $this->entityManager->getRepository(LoginTokenEntity::class)->findOneBy(['tokenHash' => $tokenHash]);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }
}
