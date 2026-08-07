<?php

namespace App\Logic\IdentityAccess\User\Manager;

use App\Logic\IdentityAccess\User\Model\User;

interface UserManagerInterface
{
    public function get(string $id): User;

    public function getByEmail(string $email): User;

    public function findByEmail(string $email): ?User;

    /**
     * @return list<User>
     */
    public function all(): array;

    public function save(User $user): User;

    public function countActiveSuperAdmins(): int;
}
