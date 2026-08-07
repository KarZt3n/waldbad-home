<?php

namespace App\Data\IdentityAccess\User\Provider;

use App\Data\IdentityAccess\User\Entity\UserEntity;
use App\Data\IdentityAccess\User\Mapper\UserMapper;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\Model\User;
use App\Logic\IdentityAccess\User\UserProviderInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class DoctrineUserProvider implements UserProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserMapper $mapper,
    ) {
    }

    public function findById(string $id): ?User
    {
        $entity = $this->entityManager->find(UserEntity::class, $id);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findByEmail(string $email): ?User
    {
        $entity = $this->entityManager->getRepository(UserEntity::class)->findOneBy(['email' => $email]);

        return $entity === null ? null : $this->mapper->toModel($entity);
    }

    public function findAll(): array
    {
        $entities = $this->entityManager->getRepository(UserEntity::class)->findBy([], ['displayName' => 'ASC']);

        return array_map($this->mapper->toModel(...), $entities);
    }

    public function countActiveSuperAdmins(): int
    {
        $users = $this->findAll();

        return count(array_filter(
            $users,
            static fn (User $user): bool => $user->active && in_array(Role::SuperAdmin, $user->roles, true),
        ));
    }
}
