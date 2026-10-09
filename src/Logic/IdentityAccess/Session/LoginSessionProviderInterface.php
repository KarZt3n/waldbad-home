<?php

namespace App\Logic\IdentityAccess\Session;

use App\Logic\IdentityAccess\Session\Model\LoginSession;

interface LoginSessionProviderInterface
{
    public function find(string $id): ?LoginSession;

    /** @return list<LoginSession> */
    public function findOpenForUser(string $userId): array;

    /** @return list<LoginSession> neueste zuerst */
    public function findRecent(int $limit): array;
}
