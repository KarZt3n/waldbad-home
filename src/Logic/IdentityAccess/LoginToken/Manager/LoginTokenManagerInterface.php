<?php

namespace App\Logic\IdentityAccess\LoginToken\Manager;

use App\Logic\IdentityAccess\LoginToken\Model\LoginToken;

interface LoginTokenManagerInterface
{
    public function findByHash(string $tokenHash): ?LoginToken;

    public function save(LoginToken $token): LoginToken;

    public function markConsumed(string $id, \DateTimeImmutable $consumedAt): void;
}
