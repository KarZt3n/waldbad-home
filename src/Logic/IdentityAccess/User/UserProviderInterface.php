<?php

namespace App\Logic\IdentityAccess\User;

use App\Logic\IdentityAccess\User\Model\User;

interface UserProviderInterface
{
    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * @return list<User>
     */
    public function findAll(): array;

    public function countActiveSuperAdmins(): int;
}
