<?php

namespace App\Logic\IdentityAccess\Session\Manager;

use App\Logic\IdentityAccess\Session\Model\LoginSession;

interface LoginSessionManagerInterface
{
    public function find(string $id): ?LoginSession;

    /** @return list<LoginSession> */
    public function findOpenForUser(string $userId): array;

    /** @return list<LoginSession> neueste zuerst */
    public function findRecent(int $limit): array;

    public function save(LoginSession $session): LoginSession;
}
