<?php

namespace App\Logic\IdentityAccess\LoginToken;

use App\Logic\IdentityAccess\LoginToken\Model\LoginToken;

interface LoginTokenProviderInterface
{
    public function findByHash(string $tokenHash): ?LoginToken;
}
