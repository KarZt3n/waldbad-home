<?php

namespace App\Logic\IdentityAccess\User\Manager;

use App\Logic\IdentityAccess\User\Exception\UserNotFoundException;
use App\Logic\IdentityAccess\User\Model\User;
use App\Logic\IdentityAccess\User\UserProcessorInterface;
use App\Logic\IdentityAccess\User\UserProviderInterface;

readonly class UserManager implements UserManagerInterface
{
    public function __construct(
        private UserProviderInterface $provider,
        private UserProcessorInterface $processor,
    ) {
    }

    public function get(string $id): User
    {
        return $this->provider->findById($id) ?? throw new UserNotFoundException($id);
    }

    public function getByEmail(string $email): User
    {
        return $this->provider->findByEmail($email) ?? throw new UserNotFoundException($email);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->provider->findByEmail($email);
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function save(User $user): User
    {
        return $this->processor->save($user);
    }

    public function countActiveSuperAdmins(): int
    {
        return $this->provider->countActiveSuperAdmins();
    }
}
