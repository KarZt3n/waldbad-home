<?php

namespace App\Data\IdentityAccess\User\Processor;

use App\Data\IdentityAccess\User\Entity\UserEntity;
use App\Data\IdentityAccess\User\Mapper\UserMapper;
use App\Logic\Common\Exception\ConcurrencyException;
use App\Logic\IdentityAccess\User\Exception\UserNotFoundException;
use App\Logic\IdentityAccess\User\Model\User;
use App\Logic\IdentityAccess\User\UserProcessorInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

readonly class DoctrineUserProcessor implements UserProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserMapper $mapper,
    ) {
    }

    public function save(User $user): User
    {
        if ($user->version === 0) {
            $entity = $this->mapper->createEntity($user);
            $this->entityManager->persist($entity);
            $this->entityManager->flush();

            return $this->mapper->toModel($entity);
        }

        $entity = $this->entityManager->find(UserEntity::class, $user->id);
        if ($entity === null) {
            throw new UserNotFoundException($user->id);
        }

        try {
            $this->entityManager->lock($entity, LockMode::OPTIMISTIC, $user->version);
            $this->mapper->updateEntity($user, $entity);
            $this->entityManager->flush();
        } catch (OptimisticLockException $exception) {
            throw new ConcurrencyException(
                'Der Benutzer wurde zwischenzeitlich geändert. Bitte laden Sie die Daten neu.',
                previous: $exception,
            );
        }

        return $this->mapper->toModel($entity);
    }
}
