<?php

namespace App\Logic\IdentityAccess\Session;

use App\Logic\IdentityAccess\Session\Model\RefreshToken;

interface RefreshTokenProviderInterface
{
    public function findByHash(string $tokenHash): ?RefreshToken;
}
