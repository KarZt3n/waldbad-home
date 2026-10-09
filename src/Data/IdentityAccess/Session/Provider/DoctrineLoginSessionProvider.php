<?php

namespace App\Data\IdentityAccess\Session\Provider;

use App\Data\IdentityAccess\Session\Entity\LoginSessionEntity;
use App\Data\IdentityAccess\Session\Mapper\LoginSessionMapper;
use App\Logic\IdentityAccess\Session\LoginSessionProviderInterface;
use App\Logic\IdentityAccess\Session\Model\LoginSession;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineLoginSessionProvider implements LoginSessionProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoginSessionMapper $mapper,
    ) {
    }

    public function find(string $id): ?LoginSession
    {
        $entity = $this->entityManager->find(LoginSessionEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findOpenForUser(string $userId): array
    {
        return array_map(
            $this->mapper->toModel(...),
            $this->entityManager->getRepository(LoginSessionEntity::class)->findBy(['userId' => $userId, 'endedAt' => null]),
        );
    }

    public function findRecent(int $limit): array
    {
        return array_map(
            $this->mapper->toModel(...),
            $this->entityManager->getRepository(LoginSessionEntity::class)->findBy([], ['startedAt' => 'DESC'], $limit),
        );
    }
}
